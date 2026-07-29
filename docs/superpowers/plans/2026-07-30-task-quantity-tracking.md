# Task Quantity Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cho phép đặt số lượng dự kiến cho công việc lặp lại chân tay, người phụ trách chỉ nhập số lượng thực tế đã làm và hệ thống tự tính phần trăm tiến độ.

**Architecture:** Ba cột mới trên bảng `tasks` (`planned_quantity`, `actual_quantity`, `quantity_unit`). Công việc có `planned_quantity` khác `null` chạy ở "chế độ đo sản lượng": `progress` trở thành giá trị dẫn xuất, tính bằng một phương thức tĩnh duy nhất `Task::progressFromQuantity()`. Một Action mới `UpdateTaskActualQuantityAction` phụ trách route riêng `tasks.quantity.update`; Action phần trăm cũ bị chặn khi công việc ở chế độ đo sản lượng, và ngược lại. Mỗi lần đổi số lượng ghi một dòng `task_activities` loại `quantity_updated`.

**Tech Stack:** Laravel 11, Pest, Inertia + Vue 3 + TypeScript, Tailwind, Spatie Laravel Permission.

**Spec:** `docs/superpowers/specs/2026-07-30-task-quantity-tracking-design.md`

## Global Constraints

- **Ngôn ngữ giao diện và thông báo lỗi: tiếng Việt.** Mọi `messages()` trong FormRequest và mọi `ValidationException` phải viết bằng tiếng Việt có dấu. Không để lọt chuỗi tiếng Anh nào ra người dùng.
- **PHP để chạy lệnh:** `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`. Lệnh `php` trần trong Bash **không chạy được** (PATH trỏ tới PHP 8.1, hỏng `platform_check`). Ví dụ: `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan test --filter=...`.
- **Kiến trúc bắt buộc:** `Route → FormRequest → Action → Model → Inertia Response`. Không đặt logic nghiệp vụ trong controller.
- **Mọi phép đọc-sửa-ghi trên `tasks` phải `lockForUpdate()` bên trong `DB::transaction`.**
- **Công thức tính tiến độ chỉ được viết một lần**, ở `Task::progressFromQuantity()`. Không copy công thức sang nơi khác.
- **Test dùng Pest**, đã có `RefreshDatabase` toàn cục trong `tests/Pest.php` — không khai báo lại. Helper sẵn có: `userWithPermissions(array $permissions = [], array $attributes = []): User`.
- **Chạy Pint trước mỗi commit:** `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" vendor/bin/pint --dirty`, và commit luôn phần Pint sửa.
- **Giới hạn số liệu:** `planned_quantity` từ 1 đến 1.000.000; `actual_quantity` từ 0 đến 1.000.000; `quantity_unit` tối đa 30 ký tự.

---

## File Structure

**Tạo mới:**

| File | Trách nhiệm |
|---|---|
| `database/migrations/2026_07_30_000000_add_quantity_columns_to_tasks_table.php` | Thêm ba cột số lượng |
| `app/Actions/Task/UpdateTaskActualQuantityAction.php` | Ghi số lượng thực tế + tính lại `progress` + ghi dòng thời gian |
| `app/Http/Requests/UpdateTaskQuantityRequest.php` | Validate và phân quyền cho route số lượng |
| `tests/Feature/Task/TaskQuantityTest.php` | Toàn bộ test của tính năng |

**Sửa:**

| File | Thay đổi |
|---|---|
| `app/Models/Task.php` | `$fillable`, `casts()`, `progressFromQuantity()`, `tracksQuantity()` |
| `app/Enums/TaskActivityType.php` | Thêm case `QuantityUpdated` |
| `app/Actions/Task/UpdateTaskProgressAction.php` | Chặn công việc ở chế độ đo sản lượng |
| `app/Actions/Task/CreateTaskAction.php` | Đặt `actual_quantity = 0` khi có chỉ tiêu |
| `app/Actions/Task/UpdateTaskAction.php` | Chuẩn hoá ba cột khi bật/sửa/tắt chỉ tiêu |
| `app/Http/Requests/StoreTaskRequest.php`, `UpdateTaskRequest.php` | Rule cho hai trường mới |
| `routes/web.php` | Route `tasks.quantity.update` |
| `app/Http/Controllers/TaskController.php` | Action `updateQuantity`, payload `edit()` |
| `database/factories/TaskFactory.php` | Ba trường mới + state `withQuantity()` |
| `resources/js/types/index.d.ts` | Ba thuộc tính mới + loại hoạt động mới |
| `resources/js/Components/TaskForm.vue` | Hai ô nhập chỉ tiêu |
| `resources/js/Pages/Tasks/Show.vue` | Form nhập số lượng thực tế |
| `resources/js/Components/TaskActivityTimeline.vue` | Hiển thị `quantity_updated` |

**Ngoài phạm vi plan này:** cập nhật `docs/srs/SRS.md` và render lại PDF — để dồn làm một lượt sau, không nằm trong chuỗi task ở dưới.

---

### Task 1: Cột dữ liệu và công thức tiến độ trên model

**Files:**
- Create: `database/migrations/2026_07_30_000000_add_quantity_columns_to_tasks_table.php`
- Modify: `app/Models/Task.php`
- Modify: `database/factories/TaskFactory.php`
- Test: `tests/Feature/Task/TaskQuantityTest.php`

**Interfaces:**
- Produces:
  - Cột `tasks.planned_quantity` (`unsignedInteger`, nullable), `tasks.actual_quantity` (`unsignedInteger`, nullable), `tasks.quantity_unit` (`string(30)`, nullable).
  - `Task::progressFromQuantity(int $planned, int $actual): int` — static, trả về `0..100`.
  - `Task::tracksQuantity(): bool` — instance, `true` khi `planned_quantity !== null`.
  - `TaskFactory::withQuantity(int $planned = 500, int $actual = 0, ?string $unit = 'hồ sơ'): static`.

- [ ] **Step 1: Viết test thất bại**

Tạo file `tests/Feature/Task/TaskQuantityTest.php`:

```php
<?php

use App\Models\Task;

test('progress is computed from planned and actual quantity', function () {
    expect(Task::progressFromQuantity(500, 120))->toBe(24)
        ->and(Task::progressFromQuantity(500, 0))->toBe(0)
        ->and(Task::progressFromQuantity(500, 500))->toBe(100);
});

test('progress from quantity rounds to the nearest whole percent', function () {
    expect(Task::progressFromQuantity(3, 1))->toBe(33)
        ->and(Task::progressFromQuantity(3, 2))->toBe(67);
});

test('progress from quantity is capped at 100 when the target is exceeded', function () {
    expect(Task::progressFromQuantity(500, 520))->toBe(100)
        ->and(Task::progressFromQuantity(500, 100000))->toBe(100);
});

test('a task tracks quantity only when a planned quantity is set', function () {
    $plain = Task::factory()->create();
    $measured = Task::factory()->withQuantity(planned: 500, actual: 120, unit: 'hồ sơ')->create();

    expect($plain->tracksQuantity())->toBeFalse()
        ->and($plain->planned_quantity)->toBeNull()
        ->and($plain->actual_quantity)->toBeNull()
        ->and($plain->quantity_unit)->toBeNull()
        ->and($measured->tracksQuantity())->toBeTrue()
        ->and($measured->planned_quantity)->toBe(500)
        ->and($measured->actual_quantity)->toBe(120)
        ->and($measured->quantity_unit)->toBe('hồ sơ');
});
```

- [ ] **Step 2: Chạy test để chắc chắn nó thất bại**

Run: `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan test --filter=TaskQuantityTest`
Expected: FAIL — `Call to undefined method App\Models\Task::progressFromQuantity()`.

- [ ] **Step 3: Viết migration**

`database/migrations/2026_07_30_000000_add_quantity_columns_to_tasks_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->unsignedInteger('planned_quantity')->nullable()->after('progress');
            $table->unsignedInteger('actual_quantity')->nullable()->after('planned_quantity');
            $table->string('quantity_unit', 30)->nullable()->after('actual_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn(['planned_quantity', 'actual_quantity', 'quantity_unit']);
        });
    }
};
```

- [ ] **Step 4: Sửa model**

Trong `app/Models/Task.php`, thêm ba tên cột vào `$fillable` ngay sau `'progress'`:

```php
        'progress',
        'planned_quantity',
        'actual_quantity',
        'quantity_unit',
```

Thêm hai cast vào `casts()` ngay sau `'progress' => 'integer',`:

```php
            'planned_quantity' => 'integer',
            'actual_quantity' => 'integer',
```

Thêm hai phương thức, đặt ngay trước `scopeOverdue`:

```php
    /**
     * Tính phần trăm tiến độ từ số lượng. Đây là nơi duy nhất giữ công thức này.
     */
    public static function progressFromQuantity(int $planned, int $actual): int
    {
        if ($planned <= 0) {
            return 0;
        }

        return min(100, (int) round($actual / $planned * 100));
    }

    public function tracksQuantity(): bool
    {
        return $this->planned_quantity !== null;
    }
```

- [ ] **Step 5: Sửa factory**

Trong `database/factories/TaskFactory.php`, thêm ba khoá vào `definition()` sau `'progress' => 0,`:

```php
            'planned_quantity' => null,
            'actual_quantity' => null,
            'quantity_unit' => null,
```

Thêm state sau `definition()`:

```php
    public function withQuantity(int $planned = 500, int $actual = 0, ?string $unit = 'hồ sơ'): static
    {
        return $this->state(fn (): array => [
            'planned_quantity' => $planned,
            'actual_quantity' => $actual,
            'quantity_unit' => $unit,
            'progress' => Task::progressFromQuantity($planned, $actual),
        ]);
    }
```

- [ ] **Step 6: Chạy test để chắc chắn nó pass**

Run: `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan test --filter=TaskQuantityTest`
Expected: PASS — 4 test.

- [ ] **Step 7: Chạy Pint và commit**

```bash
"C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pint --dirty
git add database/migrations app/Models/Task.php database/factories/TaskFactory.php tests/Feature/Task/TaskQuantityTest.php
git commit -m "feat(task): add planned and actual quantity columns"
```

---

### Task 2: Action ghi số lượng thực tế

**Files:**
- Create: `app/Actions/Task/UpdateTaskActualQuantityAction.php`
- Modify: `app/Enums/TaskActivityType.php`
- Test: `tests/Feature/Task/TaskQuantityTest.php` (bổ sung vào cuối file)

**Interfaces:**
- Consumes: `Task::progressFromQuantity(int, int): int`, `Task::tracksQuantity(): bool`, `TaskFactory::withQuantity()` (Task 1).
- Produces:
  - `TaskActivityType::QuantityUpdated` với giá trị chuỗi `'quantity_updated'`.
  - `UpdateTaskActualQuantityAction::execute(User $actor, Task $task, int $quantity): Task`.
  - Payload dòng thời gian: `['from' => int, 'to' => int, 'planned' => int, 'unit' => ?string]`.

Ghi chú: `RecordTaskActivityAction::execute(User $actor, Task $task, TaskActivityType $type, array $payload = []): TaskActivity` đã có sẵn và **không** tự mở transaction — cứ gọi bên trong transaction của Action này.

- [ ] **Step 1: Viết test thất bại**

Thêm vào cuối `tests/Feature/Task/TaskQuantityTest.php` (bổ sung import ở đầu file: `use App\Actions\Task\UpdateTaskActualQuantityAction;`, `use App\Enums\TaskActivityType;`, `use App\Enums\TaskStatus;`, `use App\Models\TaskActivity;`, `use App\Models\User;`, `use Illuminate\Validation\ValidationException;`):

```php
test('recording actual quantity updates the stored progress', function () {
    $actor = User::factory()->create();
    $task = Task::factory()
        ->withQuantity(planned: 500, actual: 0, unit: 'hồ sơ')
        ->create(['status' => TaskStatus::InProgress, 'assignee_id' => $actor->id]);

    $updated = app(UpdateTaskActualQuantityAction::class)->execute($actor, $task, 120);

    expect($updated->actual_quantity)->toBe(120)
        ->and($updated->progress)->toBe(24);
});

test('actual quantity may exceed the target while progress stops at 100', function () {
    $actor = User::factory()->create();
    $task = Task::factory()
        ->withQuantity(planned: 500, actual: 0, unit: 'hồ sơ')
        ->create(['status' => TaskStatus::InProgress, 'assignee_id' => $actor->id]);

    $updated = app(UpdateTaskActualQuantityAction::class)->execute($actor, $task, 520);

    expect($updated->actual_quantity)->toBe(520)
        ->and($updated->progress)->toBe(100);
});

test('recording actual quantity writes exactly one quantity activity row', function () {
    $actor = User::factory()->create();
    $task = Task::factory()
        ->withQuantity(planned: 500, actual: 100, unit: 'hồ sơ')
        ->create(['status' => TaskStatus::InProgress, 'assignee_id' => $actor->id]);

    app(UpdateTaskActualQuantityAction::class)->execute($actor, $task, 260);

    $activities = TaskActivity::query()->where('task_id', $task->id)->get();

    expect($activities)->toHaveCount(1)
        ->and($activities->first()->type)->toBe(TaskActivityType::QuantityUpdated)
        ->and($activities->first()->actor_id)->toBe($actor->id)
        ->and($activities->first()->payload)->toBe([
            'from' => 100,
            'to' => 260,
            'planned' => 500,
            'unit' => 'hồ sơ',
        ]);
});

test('submitting the same actual quantity records nothing', function () {
    $actor = User::factory()->create();
    $task = Task::factory()
        ->withQuantity(planned: 500, actual: 260, unit: 'hồ sơ')
        ->create(['status' => TaskStatus::InProgress, 'assignee_id' => $actor->id]);

    app(UpdateTaskActualQuantityAction::class)->execute($actor, $task, 260);

    expect(TaskActivity::query()->where('task_id', $task->id)->count())->toBe(0);
});

test('actual quantity cannot be recorded on a task without a target', function () {
    $actor = User::factory()->create();
    $task = Task::factory()->create([
        'status' => TaskStatus::InProgress,
        'assignee_id' => $actor->id,
    ]);

    expect(fn () => app(UpdateTaskActualQuantityAction::class)->execute($actor, $task, 10))
        ->toThrow(ValidationException::class, 'Công việc này không theo dõi bằng số lượng.');
});

test('actual quantity cannot be recorded unless the task is in progress', function () {
    $actor = User::factory()->create();
    $task = Task::factory()
        ->withQuantity(planned: 500, actual: 0, unit: 'hồ sơ')
        ->create(['status' => TaskStatus::Todo, 'assignee_id' => $actor->id]);

    expect(fn () => app(UpdateTaskActualQuantityAction::class)->execute($actor, $task, 10))
        ->toThrow(ValidationException::class, 'Chỉ có thể cập nhật số lượng khi công việc đang được thực hiện.');

    expect($task->fresh()->actual_quantity)->toBe(0);
});
```

- [ ] **Step 2: Chạy test để chắc chắn nó thất bại**

Run: `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan test --filter=TaskQuantityTest`
Expected: FAIL — class `UpdateTaskActualQuantityAction` không tồn tại.

- [ ] **Step 3: Thêm case vào enum**

Trong `app/Enums/TaskActivityType.php`, thêm sau `case ProgressUpdated`:

```php
    case QuantityUpdated = 'quantity_updated';
```

- [ ] **Step 4: Viết Action**

`app/Actions/Task/UpdateTaskActualQuantityAction.php`:

```php
<?php

namespace App\Actions\Task;

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateTaskActualQuantityAction
{
    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    public function execute(User $actor, Task $task, int $quantity): Task
    {
        return DB::transaction(function () use ($actor, $task, $quantity): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

            if (! $lockedTask->tracksQuantity()) {
                throw ValidationException::withMessages([
                    'actual_quantity' => 'Công việc này không theo dõi bằng số lượng.',
                ]);
            }

            if ($lockedTask->status !== TaskStatus::InProgress) {
                throw ValidationException::withMessages([
                    'actual_quantity' => 'Chỉ có thể cập nhật số lượng khi công việc đang được thực hiện.',
                ]);
            }

            $previousQuantity = (int) $lockedTask->actual_quantity;

            if ($previousQuantity === $quantity) {
                return $lockedTask;
            }

            $lockedTask->update([
                'actual_quantity' => $quantity,
                'progress' => Task::progressFromQuantity((int) $lockedTask->planned_quantity, $quantity),
            ]);

            $this->recordActivity->execute($actor, $lockedTask, TaskActivityType::QuantityUpdated, [
                'from' => $previousQuantity,
                'to' => $quantity,
                'planned' => (int) $lockedTask->planned_quantity,
                'unit' => $lockedTask->quantity_unit,
            ]);

            return $lockedTask->refresh();
        });
    }
}
```

Lưu ý: Action này cố tình **không** ghi thêm hoạt động `progress_updated` dù `progress` có đổi — một hành động của người dùng chỉ sinh một dòng thời gian.

- [ ] **Step 5: Chạy test để chắc chắn nó pass**

Run: `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan test --filter=TaskQuantityTest`
Expected: PASS — 10 test.

- [ ] **Step 6: Chạy Pint và commit**

```bash
"C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pint --dirty
git add app/Actions/Task/UpdateTaskActualQuantityAction.php app/Enums/TaskActivityType.php tests/Feature/Task/TaskQuantityTest.php
git commit -m "feat(task): record actual quantity and derive progress"
```

---

### Task 3: Hai lối vào loại trừ nhau và chuẩn hoá khi tạo/sửa việc

**Files:**
- Modify: `app/Actions/Task/UpdateTaskProgressAction.php`
- Modify: `app/Actions/Task/CreateTaskAction.php`
- Modify: `app/Actions/Task/UpdateTaskAction.php`
- Test: `tests/Feature/Task/TaskQuantityTest.php` (bổ sung)

**Interfaces:**
- Consumes: `Task::progressFromQuantity()`, `Task::tracksQuantity()` (Task 1).
- Produces: bất biến "hai lối vào loại trừ nhau" (BR-Q-05) và bộ ba cột luôn nhất quán sau khi tạo/sửa việc (BR-Q-06, BR-Q-07, BR-Q-08).

- [ ] **Step 1: Viết test thất bại**

Thêm vào cuối `tests/Feature/Task/TaskQuantityTest.php` (bổ sung import: `use App\Actions\Task\CreateTaskAction;`, `use App\Actions\Task\UpdateTaskAction;`, `use App\Actions\Task\UpdateTaskProgressAction;`, `use App\Enums\TaskPriority;`, `use App\Models\OrganizationUnit;`):

```php
test('manual progress is rejected on a task that tracks quantity', function () {
    $actor = User::factory()->create();
    $task = Task::factory()
        ->withQuantity(planned: 500, actual: 100, unit: 'hồ sơ')
        ->create(['status' => TaskStatus::InProgress, 'assignee_id' => $actor->id]);

    expect(fn () => app(UpdateTaskProgressAction::class)->execute($actor, $task, 90))
        ->toThrow(ValidationException::class, 'Công việc này theo dõi bằng số lượng, hãy cập nhật số lượng thực tế.');

    expect($task->fresh()->progress)->toBe(20);
});

test('manual progress still works on a task without a target', function () {
    $actor = User::factory()->create();
    $task = Task::factory()->create([
        'status' => TaskStatus::InProgress,
        'assignee_id' => $actor->id,
        'progress' => 20,
    ]);

    app(UpdateTaskProgressAction::class)->execute($actor, $task, 65);

    expect($task->fresh()->progress)->toBe(65);
});

test('creating a task with a target starts the actual quantity at zero', function () {
    $actor = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();

    $task = app(CreateTaskAction::class)->execute($actor, [
        'organization_unit_id' => $unit->id,
        'title' => 'Nhập hồ sơ tháng 8',
        'description' => null,
        'priority' => TaskPriority::Medium,
        'planned_quantity' => 500,
        'quantity_unit' => 'hồ sơ',
    ]);

    expect($task->planned_quantity)->toBe(500)
        ->and($task->actual_quantity)->toBe(0)
        ->and($task->quantity_unit)->toBe('hồ sơ')
        ->and($task->progress)->toBe(0);
});

test('lowering the target recomputes progress from the recorded quantity', function () {
    $actor = User::factory()->create();
    $task = Task::factory()
        ->withQuantity(planned: 500, actual: 200, unit: 'hồ sơ')
        ->create(['status' => TaskStatus::InProgress]);

    expect($task->progress)->toBe(40);

    app(UpdateTaskAction::class)->execute($actor, $task, [
        'organization_unit_id' => $task->organization_unit_id,
        'title' => $task->title,
        'description' => null,
        'priority' => TaskPriority::Medium,
        'planned_quantity' => 400,
        'quantity_unit' => 'hồ sơ',
    ]);

    expect($task->fresh()->planned_quantity)->toBe(400)
        ->and($task->fresh()->actual_quantity)->toBe(200)
        ->and($task->fresh()->progress)->toBe(50);
});

test('clearing the target keeps the last progress and empties the quantity columns', function () {
    $actor = User::factory()->create();
    $task = Task::factory()
        ->withQuantity(planned: 500, actual: 200, unit: 'hồ sơ')
        ->create(['status' => TaskStatus::InProgress]);

    app(UpdateTaskAction::class)->execute($actor, $task, [
        'organization_unit_id' => $task->organization_unit_id,
        'title' => $task->title,
        'description' => null,
        'priority' => TaskPriority::Medium,
        'planned_quantity' => null,
    ]);

    $fresh = $task->fresh();

    expect($fresh->planned_quantity)->toBeNull()
        ->and($fresh->actual_quantity)->toBeNull()
        ->and($fresh->quantity_unit)->toBeNull()
        ->and($fresh->progress)->toBe(40);
});

test('adding a target to an existing task starts it at zero without an activity row', function () {
    $actor = User::factory()->create();
    $task = Task::factory()->create(['status' => TaskStatus::InProgress]);

    app(UpdateTaskAction::class)->execute($actor, $task, [
        'organization_unit_id' => $task->organization_unit_id,
        'title' => $task->title,
        'description' => null,
        'priority' => TaskPriority::Medium,
        'planned_quantity' => 500,
        'quantity_unit' => 'hồ sơ',
    ]);

    $fresh = $task->fresh();

    expect($fresh->planned_quantity)->toBe(500)
        ->and($fresh->actual_quantity)->toBe(0)
        ->and(TaskActivity::query()
            ->where('task_id', $task->id)
            ->where('type', TaskActivityType::QuantityUpdated)
            ->count())->toBe(0);
});
```

- [ ] **Step 2: Chạy test để chắc chắn nó thất bại**

Run: `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan test --filter=TaskQuantityTest`
Expected: FAIL — `UpdateTaskProgressAction` vẫn cho ghi đè, và các cột số lượng không được chuẩn hoá.

- [ ] **Step 3: Chặn lối vào phần trăm**

Trong `app/Actions/Task/UpdateTaskProgressAction.php`, thêm ngay sau dòng `$lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);` và **trước** kiểm tra trạng thái:

```php
            if ($lockedTask->tracksQuantity()) {
                throw ValidationException::withMessages([
                    'progress' => 'Công việc này theo dõi bằng số lượng, hãy cập nhật số lượng thực tế.',
                ]);
            }
```

- [ ] **Step 4: Chuẩn hoá khi tạo việc**

Trong `app/Actions/Task/CreateTaskAction.php`, thay lời gọi `Task::create` bằng:

```php
            $plannedQuantity = $data['planned_quantity'] ?? null;

            $task = Task::create([
                ...$data,
                'creator_id' => $actor->id,
                'status' => TaskStatus::Draft,
                'progress' => 0,
                'planned_quantity' => $plannedQuantity,
                'actual_quantity' => $plannedQuantity === null ? null : 0,
                'quantity_unit' => $plannedQuantity === null ? null : ($data['quantity_unit'] ?? null),
            ]);
```

- [ ] **Step 5: Chuẩn hoá khi sửa việc**

Trong `app/Actions/Task/UpdateTaskAction.php`, thay dòng `$lockedTask->update($data);` bằng:

```php
            $lockedTask->update($this->withNormalizedQuantity($lockedTask, $data));
```

Thêm phương thức riêng, đặt ngay sau `execute()`:

```php
    /**
     * Giữ ba cột số lượng luôn nhất quán: cùng có giá trị hoặc cùng rỗng,
     * và progress luôn khớp với số lượng hiện tại.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withNormalizedQuantity(Task $task, array $data): array
    {
        if (! array_key_exists('planned_quantity', $data)) {
            return $data;
        }

        $planned = $data['planned_quantity'] === null ? null : (int) $data['planned_quantity'];

        if ($planned === null) {
            // Tắt chế độ đo sản lượng: giữ nguyên progress đã báo cáo gần nhất.
            $data['planned_quantity'] = null;
            $data['actual_quantity'] = null;
            $data['quantity_unit'] = null;

            return $data;
        }

        $actual = $task->tracksQuantity() ? (int) $task->actual_quantity : 0;

        $data['planned_quantity'] = $planned;
        $data['actual_quantity'] = $actual;
        $data['quantity_unit'] = $data['quantity_unit'] ?? null;
        $data['progress'] = Task::progressFromQuantity($planned, $actual);

        return $data;
    }
```

- [ ] **Step 6: Chạy test để chắc chắn nó pass**

Run: `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan test --filter=TaskQuantityTest`
Expected: PASS — 16 test.

- [ ] **Step 7: Chạy lại toàn bộ test công việc để bắt hồi quy**

Run: `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan test --filter=Task`
Expected: PASS toàn bộ. Nếu có test cũ đỏ, sửa test cũ cho khớp hành vi mới **chỉ khi** hành vi mới đúng theo spec; nếu không, sửa code.

- [ ] **Step 8: Chạy Pint và commit**

```bash
"C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pint --dirty
git add app/Actions/Task tests/Feature/Task/TaskQuantityTest.php
git commit -m "feat(task): keep quantity and percent progress paths exclusive"
```

---

### Task 4: Route, FormRequest và controller

**Files:**
- Create: `app/Http/Requests/UpdateTaskQuantityRequest.php`
- Modify: `app/Http/Requests/StoreTaskRequest.php`
- Modify: `app/Http/Requests/UpdateTaskRequest.php`
- Modify: `routes/web.php:46`
- Modify: `app/Http/Controllers/TaskController.php`
- Test: `tests/Feature/Task/TaskQuantityTest.php` (bổ sung)

**Interfaces:**
- Consumes: `UpdateTaskActualQuantityAction::execute()` (Task 2).
- Produces:
  - Route tên `tasks.quantity.update`, method PATCH, uri `tasks/{task}/quantity`.
  - `TaskController::updateQuantity(UpdateTaskQuantityRequest $request, Task $task, UpdateTaskActualQuantityAction $action): RedirectResponse`.
  - Payload `Tasks/Edit` bổ sung `planned_quantity`, `quantity_unit`.

- [ ] **Step 1: Viết test thất bại**

Thêm vào cuối `tests/Feature/Task/TaskQuantityTest.php` (bổ sung import: `use App\Enums\PermissionName;`):

```php
test('an assignee can record actual quantity through the route', function () {
    $actor = userWithPermissions([PermissionName::TaskView->value]);
    $task = Task::factory()
        ->withQuantity(planned: 500, actual: 0, unit: 'hồ sơ')
        ->create(['status' => TaskStatus::InProgress, 'assignee_id' => $actor->id]);

    $this->actingAs($actor)
        ->patch(route('tasks.quantity.update', $task), ['actual_quantity' => 120])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($task->fresh()->actual_quantity)->toBe(120)
        ->and($task->fresh()->progress)->toBe(24);
});

test('only the assignee can record actual quantity', function () {
    $stranger = userWithPermissions([PermissionName::TaskView->value]);
    $task = Task::factory()
        ->withQuantity(planned: 500, actual: 0, unit: 'hồ sơ')
        ->create(['status' => TaskStatus::InProgress, 'assignee_id' => User::factory()->create()->id]);

    $this->actingAs($stranger)
        ->patch(route('tasks.quantity.update', $task), ['actual_quantity' => 120])
        ->assertForbidden();

    expect($task->fresh()->actual_quantity)->toBe(0);
});

test('actual quantity must be a whole number within range', function () {
    $actor = userWithPermissions([PermissionName::TaskView->value]);
    $task = Task::factory()
        ->withQuantity(planned: 500, actual: 0, unit: 'hồ sơ')
        ->create(['status' => TaskStatus::InProgress, 'assignee_id' => $actor->id]);

    $this->actingAs($actor)
        ->patch(route('tasks.quantity.update', $task), ['actual_quantity' => -1])
        ->assertSessionHasErrors('actual_quantity');

    $this->actingAs($actor)
        ->patch(route('tasks.quantity.update', $task), ['actual_quantity' => 1000001])
        ->assertSessionHasErrors('actual_quantity');

    expect($task->fresh()->actual_quantity)->toBe(0);
});

test('the planned quantity must be a positive number within range', function () {
    $actor = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskCreate->value,
    ]);
    $unit = OrganizationUnit::factory()->create();

    $payload = [
        'organization_unit_id' => $unit->id,
        'title' => 'Nhập hồ sơ tháng 8',
        'priority' => TaskPriority::Medium->value,
    ];

    $this->actingAs($actor)
        ->post(route('tasks.store'), [...$payload, 'planned_quantity' => 0])
        ->assertSessionHasErrors('planned_quantity');

    $this->actingAs($actor)
        ->post(route('tasks.store'), [...$payload, 'planned_quantity' => 1000001])
        ->assertSessionHasErrors('planned_quantity');
});

test('a unit cannot be submitted without a planned quantity', function () {
    $actor = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskCreate->value,
    ]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($actor)
        ->post(route('tasks.store'), [
            'organization_unit_id' => $unit->id,
            'title' => 'Nhập hồ sơ tháng 8',
            'priority' => TaskPriority::Medium->value,
            'quantity_unit' => 'hồ sơ',
        ])
        ->assertSessionHasErrors('quantity_unit');
});

test('actual quantity cannot be set through the task crud form', function () {
    $actor = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskCreate->value,
    ]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($actor)
        ->post(route('tasks.store'), [
            'organization_unit_id' => $unit->id,
            'title' => 'Nhập hồ sơ tháng 8',
            'priority' => TaskPriority::Medium->value,
            'planned_quantity' => 500,
            'actual_quantity' => 480,
        ])
        ->assertSessionHasErrors('actual_quantity');
});
```

- [ ] **Step 2: Chạy test để chắc chắn nó thất bại**

Run: `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan test --filter=TaskQuantityTest`
Expected: FAIL — route `tasks.quantity.update` chưa tồn tại.

- [ ] **Step 3: Viết FormRequest mới**

`app/Http/Requests/UpdateTaskQuantityRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTaskQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateProgress', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'actual_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'actual_quantity.required' => 'Vui lòng nhập số lượng đã làm.',
            'actual_quantity.integer' => 'Số lượng đã làm phải là số nguyên.',
            'actual_quantity.min' => 'Số lượng đã làm không được là số âm.',
            'actual_quantity.max' => 'Số lượng đã làm không được vượt quá 1.000.000.',
        ];
    }
}
```

- [ ] **Step 4: Bổ sung rule cho form tạo và sửa việc**

Trong **cả hai** file `app/Http/Requests/StoreTaskRequest.php` và `app/Http/Requests/UpdateTaskRequest.php`, thêm vào mảng `rules()` ngay sau dòng `'due_at' => ['nullable', 'date'],`:

```php
            'planned_quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'quantity_unit' => [
                Rule::prohibitedIf(fn (): bool => ! $this->filled('planned_quantity')),
                'nullable',
                'string',
                'max:30',
            ],
            'actual_quantity' => ['prohibited'],
```

Và thêm `messages()` vào **cả hai** file (nếu file chưa có phương thức này thì tạo mới, đặt sau `rules()`):

```php
    public function messages(): array
    {
        return [
            'planned_quantity.integer' => 'Số lượng dự kiến phải là số nguyên.',
            'planned_quantity.min' => 'Số lượng dự kiến phải lớn hơn 0.',
            'planned_quantity.max' => 'Số lượng dự kiến không được vượt quá 1.000.000.',
            'quantity_unit.prohibited' => 'Chỉ nhập đơn vị khi công việc có số lượng dự kiến.',
            'quantity_unit.max' => 'Đơn vị không được dài quá 30 ký tự.',
            'actual_quantity.prohibited' => 'Số lượng đã làm chỉ được cập nhật ở trang chi tiết công việc.',
        ];
    }
```

`Rule` đã được import sẵn ở cả hai file.

- [ ] **Step 5: Thêm route**

Trong `routes/web.php`, ngay sau dòng route `tasks.progress.update`:

```php
    Route::patch('tasks/{task}/quantity', [TaskController::class, 'updateQuantity'])->name('tasks.quantity.update');
```

- [ ] **Step 6: Thêm controller action**

Trong `app/Http/Controllers/TaskController.php`, thêm ngay sau `updateProgress()`:

```php
    public function updateQuantity(
        UpdateTaskQuantityRequest $request,
        Task $task,
        UpdateTaskActualQuantityAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $task, $request->integer('actual_quantity'));

        return Redirect::back()->with('success', 'Đã cập nhật số lượng đã làm.');
    }
```

Thêm hai `use` ở đầu file: `use App\Actions\Task\UpdateTaskActualQuantityAction;` và `use App\Http\Requests\UpdateTaskQuantityRequest;`.

Trong `edit()`, bổ sung hai khoá vào mảng `$task->only([...])` sau `'due_at'`:

```php
                'planned_quantity',
                'quantity_unit',
```

`show()` không cần sửa: payload đã dùng `...$task->toArray()` nên ba cột mới tự có mặt.

- [ ] **Step 7: Chạy test để chắc chắn nó pass**

Run: `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan test --filter=TaskQuantityTest`
Expected: PASS — 22 test.

- [ ] **Step 8: Chạy toàn bộ test backend**

Run: `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan test`
Expected: PASS toàn bộ.

- [ ] **Step 9: Chạy Pint và commit**

```bash
"C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/pint --dirty
git add app/Http routes/web.php tests/Feature/Task/TaskQuantityTest.php
git commit -m "feat(task): expose the actual quantity route"
```

---

### Task 5: Nhập chỉ tiêu ở form tạo và sửa việc

**Files:**
- Modify: `resources/js/types/index.d.ts`
- Modify: `resources/js/Components/TaskForm.vue`

**Interfaces:**
- Consumes: rule `planned_quantity`, `quantity_unit` (Task 4).
- Produces: `Task` trong TypeScript có `planned_quantity: number | null`, `actual_quantity: number | null`, `quantity_unit: string | null`.

Không có test tự động cho frontend trong dự án này; kiểm chứng bằng `npm run build`.

- [ ] **Step 1: Bổ sung kiểu**

Trong `resources/js/types/index.d.ts`, trong `interface Task`, thêm ngay sau `progress: number;`:

```ts
    planned_quantity: number | null;
    actual_quantity: number | null;
    quantity_unit: string | null;
```

Trong union `TaskActivityType`, thêm một nhánh sau `| 'progress_updated'`:

```ts
    | 'quantity_updated'
```

- [ ] **Step 2: Mở rộng prop và form state của `TaskForm.vue`**

Trong `resources/js/Components/TaskForm.vue`, sửa dòng khai báo prop `task` (dòng 13) để thêm ba khoá:

```ts
    task?: Pick<
        Task,
        | 'id'
        | 'organization_unit_id'
        | 'assignee_id'
        | 'title'
        | 'description'
        | 'priority'
        | 'due_at'
        | 'planned_quantity'
        | 'quantity_unit'
    >;
```

Trong `useForm`, thêm sau `due_at`:

```ts
    planned_quantity: props.task?.planned_quantity ?? null,
    quantity_unit: props.task?.quantity_unit ?? '',
```

- [ ] **Step 3: Chuẩn hoá payload trước khi gửi**

Trong `handleSubmit`, thêm ngay trước lời gọi `submit(payload);`:

```ts
    if (!payload.planned_quantity) {
        payload.planned_quantity = null;
        delete payload.quantity_unit;
    } else if (!payload.quantity_unit) {
        payload.quantity_unit = null;
    }
```

Lý do xoá hẳn `quantity_unit` khi không có chỉ tiêu: rule `prohibitedIf` ở backend sẽ báo lỗi nếu trường này có mặt kèm giá trị.

- [ ] **Step 4: Thêm hai ô nhập vào template**

Trong `resources/js/Components/TaskForm.vue`, ngay sau khối `<div>` chứa ô `due_at` (khoảng dòng 170–173), thêm:

```html
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <InputLabel for="planned_quantity" value="Số lượng dự kiến" />
                        <TextInput
                            id="planned_quantity"
                            v-model.number="form.planned_quantity"
                            type="number"
                            min="1"
                            max="1000000"
                            class="mt-1 block w-full"
                            placeholder="Ví dụ: 500"
                        />
                        <InputError class="mt-2" :message="form.errors.planned_quantity" />
                    </div>
                    <div>
                        <InputLabel for="quantity_unit" value="Đơn vị" />
                        <TextInput
                            id="quantity_unit"
                            v-model="form.quantity_unit"
                            type="text"
                            maxlength="30"
                            class="mt-1 block w-full"
                            placeholder="hồ sơ, cuộc gọi..."
                            :disabled="!form.planned_quantity"
                        />
                        <InputError class="mt-2" :message="form.errors.quantity_unit" />
                    </div>
                </div>
                <p class="text-xs text-slate-500">
                    Để trống số lượng dự kiến nếu công việc theo dõi tiến độ bằng phần trăm.
                </p>
```

- [ ] **Step 5: Kiểm tra build**

Run: `npm run build`
Expected: build thành công, không lỗi TypeScript.

- [ ] **Step 6: Commit**

```bash
git add resources/js/types/index.d.ts resources/js/Components/TaskForm.vue
git commit -m "feat(task): capture the planned quantity on the task form"
```

---

### Task 6: Nhập số lượng thực tế ở trang chi tiết và hiển thị dòng thời gian

**Files:**
- Modify: `resources/js/Pages/Tasks/Show.vue`
- Modify: `resources/js/Components/TaskActivityTimeline.vue`

**Interfaces:**
- Consumes: route `tasks.quantity.update` (Task 4), kiểu `Task` mở rộng và `'quantity_updated'` (Task 5).
- Produces: giao diện cuối cùng của tính năng.

- [ ] **Step 1: Thêm form số lượng vào `Show.vue`**

Trong phần `<script setup>`, thêm ngay sau khai báo `progressForm`:

```ts
const quantityForm = useForm({
    value: props.task.actual_quantity ?? 0,
});

const tracksQuantity = computed(() => props.task.planned_quantity !== null);

const quantitySummary = computed(() => {
    if (!tracksQuantity.value) {
        return '';
    }

    const unit = props.task.quantity_unit ? ` ${props.task.quantity_unit}` : '';

    return `${props.task.actual_quantity ?? 0}/${props.task.planned_quantity}${unit}`;
});

const updateQuantity = () => {
    quantityForm
        .transform((data) => ({ actual_quantity: data.value }))
        .patch(route('tasks.quantity.update', props.task.id), {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.actual_quantity) {
                    quantityForm.setError('value', errors.actual_quantity);
                }
            },
        });
};
```

Bổ sung `computed` vào import từ `vue` ở đầu file nếu chưa có.

- [ ] **Step 2: Hiển thị con số ở ô "Tiến độ"**

Trong `<dd>` của mục "Tiến độ" (khoảng dòng 409), thêm ngay **trước** thanh phần trăm:

```html
                            <p v-if="tracksQuantity" class="mb-1.5 text-sm font-bold text-slate-700">
                                {{ quantitySummary }}
                            </p>
```

- [ ] **Step 3: Rẽ nhánh form cập nhật**

Trong form `v-if="actions.updateProgress"`, đổi tiêu đề và phần nhập liệu. Thay dòng `<h3 class="text-sm font-bold text-brand-950">Cập nhật tiến độ</h3>` bằng:

```html
                            <h3 class="text-sm font-bold text-brand-950">
                                {{ tracksQuantity ? 'Cập nhật số lượng' : 'Cập nhật tiến độ' }}
                            </h3>
```

Bọc khối nhập phần trăm sẵn có (`<div class="mt-4 flex items-center gap-3">…</div>` cùng `<InputError :message="progressForm.errors.value" />` và nút bấm) trong `<template v-if="!tracksQuantity">`, rồi thêm nhánh còn lại ngay sau:

```html
                    <template v-else>
                        <label class="mt-4 block">
                            <span class="text-xs font-semibold text-brand-900">Số lượng đã làm</span>
                            <span class="relative mt-1 block">
                                <input
                                    v-model.number="quantityForm.value"
                                    type="number"
                                    min="0"
                                    max="1000000"
                                    class="app-field h-10 w-full py-2 pr-16 text-sm font-bold"
                                    aria-label="Số lượng đã làm"
                                />
                                <span
                                    v-if="task.quantity_unit"
                                    class="pointer-events-none absolute right-3 top-1/2 max-w-[4.5rem] -translate-y-1/2 truncate text-xs font-semibold text-slate-400"
                                >
                                    {{ task.quantity_unit }}
                                </span>
                            </span>
                        </label>

                        <p class="mt-1.5 text-xs text-brand-800/70">
                            Chỉ tiêu {{ task.planned_quantity }}{{ task.quantity_unit ? ` ${task.quantity_unit}` : '' }}. Phần trăm tiến độ được tính tự động.
                        </p>

                        <InputError class="mt-2" :message="quantityForm.errors.value" />

                        <button
                            type="submit"
                            class="app-button-primary mt-4 w-full justify-center"
                            :disabled="quantityForm.processing"
                        >
                            <AppIcon name="check" class="size-4" />
                            {{ quantityForm.processing ? 'Đang lưu...' : 'Lưu số lượng' }}
                        </button>
                    </template>
```

Đổi `@submit.prevent="updateProgress"` trên thẻ `<form>` thành:

```html
                    @submit.prevent="tracksQuantity ? updateQuantity() : updateProgress()"
```

- [ ] **Step 4: Hiển thị hoạt động mới trên dòng thời gian**

Trong `resources/js/Components/TaskActivityTimeline.vue`:

Thêm `'quantity_updated',` vào mảng `KNOWN_TYPES` (sau `'progress_updated'`).

Thêm vào `iconFor`: `quantity_updated: 'tasks',` — và bổ sung `'tasks'` vào union `ActivityIcon`.

Thêm vào `toneFor`: `quantity_updated: 'bg-sky-50 text-sky-700',`.

Thêm nhánh vào `describe`, ngay sau nhánh `'progress_updated'`:

```ts
        case 'quantity_updated': {
            const unit = payload.unit ? ` ${payload.unit as string}` : '';

            return `cập nhật sản lượng từ ${payload.from} lên ${payload.to}/${payload.planned}${unit}`;
        }
```

- [ ] **Step 5: Kiểm tra build**

Run: `npm run build`
Expected: build thành công, không lỗi TypeScript.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Tasks/Show.vue resources/js/Components/TaskActivityTimeline.vue
git commit -m "feat(task): record actual quantity from the task detail page"
```

---

## Kiểm tra cuối cùng

- [ ] `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" artisan test` — toàn bộ xanh.
- [ ] `& "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" vendor/bin/pint --test` — không còn file cần định dạng.
- [ ] `npm run build` — thành công.
- [ ] `git status` — cây làm việc sạch.
