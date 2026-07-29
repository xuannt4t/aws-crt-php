# Activity Timeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gom mọi hoạt động của một công việc (tạo, đổi trạng thái, phân công, tiến độ, bình luận, tệp đính kèm) vào một dòng thời gian duy nhất trên trang chi tiết (FR-TASK-11).

**Architecture:** Bảng `task_activities` append-only là nguồn duy nhất của timeline. Mọi Action nghiệp vụ gọi `RecordTaskActivityAction` từ bên trong transaction sẵn có của mình. Backend trả dữ liệu thô (`type` + `payload`), frontend dựng câu tiếng Việt.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL 8, Pest, Vue 3 + Inertia + TypeScript, Tailwind.

**Spec:** `docs/superpowers/specs/2026-07-29-sprint2-activity-timeline-design.md`

## Global Constraints

- Luồng bắt buộc: `Route → Controller → FormRequest → Action → Model → Inertia Response`. Controller không chứa business rule.
- Class mới khai báo `final`, có type hint và return type, tuân thủ PSR-12 (`./vendor/bin/pint`).
- Bảng `task_activities` là append-only: không `updated_at`, không soft delete, không sửa bản ghi đã tạo.
- `RecordTaskActivityAction` **không tự mở transaction** — luôn được gọi từ trong transaction của Action nghiệp vụ.
- Chỉ ghi hoạt động khi giá trị **thực sự thay đổi** (phân công, tiến độ).
- `payload` lưu sẵn tên và giá trị tại thời điểm xảy ra, để dòng lịch sử vẫn đọc được sau khi bản ghi gốc bị xoá mềm.
- Backend không trả câu đã ghép; câu chữ tiếng Việt dựng ở frontend từ `type` + `payload`.
- Mọi chuỗi hiển thị cho người dùng bằng tiếng Việt.
- Test framework: Pest. Chạy một file: `php artisan test tests/Feature/Task/TaskActivityTest.php`.
- Mỗi task kết thúc bằng một commit riêng. Không dùng `--no-verify`.

---

### Task 1: Nền tảng — enum, bảng, model, Action ghi hoạt động

**Files:**
- Create: `app/Enums/TaskActivityType.php`
- Create: `database/migrations/2026_07_29_120000_create_task_activities_table.php`
- Create: `app/Models/TaskActivity.php`
- Create: `database/factories/TaskActivityFactory.php`
- Create: `app/Actions/Task/RecordTaskActivityAction.php`
- Modify: `app/Models/Task.php` (thêm quan hệ `activities()` ngay dưới `attachments()`)
- Test: `tests/Feature/Task/TaskActivityTest.php`

**Interfaces:**
- Consumes: `App\Models\Task`, `App\Models\User` (đã có).
- Produces:
  - `TaskActivityType` — backed enum string với các case `Created = 'created'`, `StatusChanged = 'status_changed'`, `Assigned = 'assigned'`, `ProgressUpdated = 'progress_updated'`, `Commented = 'commented'`, `AttachmentAdded = 'attachment_added'`, `AttachmentRemoved = 'attachment_removed'`.
  - `TaskActivity` với `$fillable = ['task_id','actor_id','type','payload']`, quan hệ `task()`, `actor()`.
  - `RecordTaskActivityAction::execute(User $actor, Task $task, TaskActivityType $type, array $payload = []): TaskActivity`.
  - `Task::activities(): HasMany` sắp xếp `latest('id')`.

- [ ] **Step 1: Viết test thất bại**

Tạo `tests/Feature/Task/TaskActivityTest.php`:

```php
<?php

use App\Actions\Task\RecordTaskActivityAction;
use App\Enums\TaskActivityType;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;

test('recording an activity stores the type, actor and payload', function () {
    $actor = User::factory()->create();
    $task = Task::factory()->create();

    $activity = app(RecordTaskActivityAction::class)->execute(
        $actor,
        $task,
        TaskActivityType::ProgressUpdated,
        ['from' => 20, 'to' => 65],
    );

    expect($activity->task_id)->toBe($task->id)
        ->and($activity->actor_id)->toBe($actor->id)
        ->and($activity->type)->toBe(TaskActivityType::ProgressUpdated)
        ->and($activity->payload)->toBe(['from' => 20, 'to' => 65]);

    $this->assertDatabaseHas('task_activities', [
        'task_id' => $task->id,
        'actor_id' => $actor->id,
        'type' => 'progress_updated',
    ]);
});

test('an activity without payload stores null', function () {
    $activity = app(RecordTaskActivityAction::class)->execute(
        User::factory()->create(),
        Task::factory()->create(),
        TaskActivityType::Created,
    );

    expect($activity->payload)->toBeNull();
});

test('a task exposes its activities newest first', function () {
    $task = Task::factory()->create();

    $older = TaskActivity::factory()->for($task)->create();
    $newer = TaskActivity::factory()->for($task)->create();

    expect($task->activities()->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

test('an activity keeps its actor after the account is soft deleted', function () {
    $actor = User::factory()->create();
    $activity = TaskActivity::factory()->for($actor, 'actor')->create();

    $actor->delete();

    expect($activity->fresh()->actor->id)->toBe($actor->id);
});
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run: `php artisan test tests/Feature/Task/TaskActivityTest.php`
Expected: FAIL với `Class "App\Enums\TaskActivityType" not found`.

- [ ] **Step 3: Viết enum**

Tạo `app/Enums/TaskActivityType.php`:

```php
<?php

namespace App\Enums;

enum TaskActivityType: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case Assigned = 'assigned';
    case ProgressUpdated = 'progress_updated';
    case Commented = 'commented';
    case AttachmentAdded = 'attachment_added';
    case AttachmentRemoved = 'attachment_removed';
}
```

- [ ] **Step 4: Viết migration**

Tạo `database/migrations/2026_07_29_120000_create_task_activities_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 40);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['task_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activities');
    }
};
```

- [ ] **Step 5: Viết model**

Tạo `app/Models/TaskActivity.php`:

```php
<?php

namespace App\Models;

use App\Enums\TaskActivityType;
use Database\Factories\TaskActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TaskActivity extends Model
{
    /** @use HasFactory<TaskActivityFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'task_id',
        'actor_id',
        'type',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'type' => TaskActivityType::class,
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
```

- [ ] **Step 6: Viết factory**

Tạo `database/factories/TaskActivityFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\TaskActivityType;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskActivity>
 */
final class TaskActivityFactory extends Factory
{
    protected $model = TaskActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'actor_id' => User::factory(),
            'type' => TaskActivityType::Created->value,
            'payload' => null,
        ];
    }
}
```

- [ ] **Step 7: Viết Action ghi hoạt động**

Tạo `app/Actions/Task/RecordTaskActivityAction.php`:

```php
<?php

namespace App\Actions\Task;

use App\Enums\TaskActivityType;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;

final class RecordTaskActivityAction
{
    /**
     * Không tự mở transaction: Action này luôn được gọi từ bên trong transaction
     * của Action nghiệp vụ, để hoạt động và dữ liệu cùng thành công hoặc cùng huỷ.
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(
        User $actor,
        Task $task,
        TaskActivityType $type,
        array $payload = [],
    ): TaskActivity {
        return $task->activities()->create([
            'actor_id' => $actor->id,
            'type' => $type,
            'payload' => $payload === [] ? null : $payload,
        ]);
    }
}
```

- [ ] **Step 8: Thêm quan hệ vào Task**

Trong `app/Models/Task.php`, ngay dưới method `attachments()`:

```php
    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->latest('id');
    }
```

- [ ] **Step 9: Chạy test để xác nhận pass**

Run: `php artisan test tests/Feature/Task/TaskActivityTest.php`
Expected: PASS, 4 test.

- [ ] **Step 10: Commit**

```bash
./vendor/bin/pint app/ database/factories/
git add app/Enums/TaskActivityType.php database/migrations/2026_07_29_120000_create_task_activities_table.php app/Models/TaskActivity.php database/factories/TaskActivityFactory.php app/Actions/Task/RecordTaskActivityAction.php app/Models/Task.php tests/Feature/Task/TaskActivityTest.php
git commit -m "feat(task): add task activity model and recorder"
```

---

### Task 2: Ghi hoạt động tạo công việc và đổi trạng thái

**Files:**
- Modify: `app/Actions/Task/CreateTaskAction.php`
- Modify: `app/Actions/Task/TransitionTaskStatusAction.php`
- Test: `tests/Feature/Task/TaskActivityTest.php` (thêm test vào cuối file)

**Interfaces:**
- Consumes: `RecordTaskActivityAction::execute(User $actor, Task $task, TaskActivityType $type, array $payload = []): TaskActivity` (Task 1).
- Produces: hoạt động `created` (không payload) và `status_changed` (payload `from`, `to` là giá trị chuỗi của `TaskStatus`).

- [ ] **Step 1: Viết test thất bại**

Thêm vào cuối `tests/Feature/Task/TaskActivityTest.php`:

```php
test('creating a task records a created activity', function () {
    $creator = userWithPermissions([App\Enums\PermissionName::TaskCreate->value]);
    $unit = App\Models\OrganizationUnit::factory()->create();

    $task = app(App\Actions\Task\CreateTaskAction::class)->execute($creator, [
        'organization_unit_id' => $unit->id,
        'title' => 'Chuẩn bị báo cáo quý',
        'priority' => App\Enums\TaskPriority::Medium->value,
    ]);

    $activity = TaskActivity::query()->where('task_id', $task->id)->sole();

    expect($activity->type)->toBe(TaskActivityType::Created)
        ->and($activity->actor_id)->toBe($creator->id)
        ->and($activity->payload)->toBeNull();
});

test('a status transition records an activity and keeps the status history', function () {
    $actor = User::factory()->create();
    $assignee = User::factory()->create();
    $task = Task::factory()->create([
        'status' => App\Enums\TaskStatus::Draft->value,
        'assignee_id' => $assignee->id,
    ]);

    app(App\Actions\Task\TransitionTaskStatusAction::class)->execute(
        $actor,
        $task,
        App\Enums\TaskStatus::Todo,
        App\Enums\TaskStatus::Draft,
    );

    $activity = TaskActivity::query()->where('task_id', $task->id)->sole();

    expect($activity->type)->toBe(TaskActivityType::StatusChanged)
        ->and($activity->actor_id)->toBe($actor->id)
        ->and($activity->payload)->toBe(['from' => 'draft', 'to' => 'todo']);

    $this->assertDatabaseHas('task_status_histories', [
        'task_id' => $task->id,
        'from_status' => 'draft',
        'to_status' => 'todo',
    ]);
});

test('a rejected status transition records no activity', function () {
    $task = Task::factory()->create(['status' => App\Enums\TaskStatus::Draft->value]);

    expect(fn () => app(App\Actions\Task\TransitionTaskStatusAction::class)->execute(
        User::factory()->create(),
        $task,
        App\Enums\TaskStatus::WaitingReview,
    ))->toThrow(Illuminate\Validation\ValidationException::class);

    expect(TaskActivity::query()->count())->toBe(0);
});
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run: `php artisan test tests/Feature/Task/TaskActivityTest.php --filter="records a created activity"`
Expected: FAIL — không có bản ghi nào trong `task_activities` nên `sole()` ném `NoItemsFoundException`.

- [ ] **Step 3: Ghi hoạt động khi tạo công việc**

Sửa `app/Actions/Task/CreateTaskAction.php` thành:

```php
<?php

namespace App\Actions\Task;

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskDescriptionSanitizer;
use Illuminate\Support\Facades\DB;

final class CreateTaskAction
{
    public function __construct(
        private readonly TaskDescriptionSanitizer $descriptionSanitizer,
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): Task
    {
        $data['description'] = $this->descriptionSanitizer->sanitize($data['description'] ?? null);

        return DB::transaction(function () use ($actor, $data): Task {
            $task = Task::create([
                ...$data,
                'creator_id' => $actor->id,
                'status' => TaskStatus::Draft,
                'progress' => 0,
            ]);

            $this->recordActivity->execute($actor, $task, TaskActivityType::Created);

            return $task;
        });
    }
}
```

- [ ] **Step 4: Ghi hoạt động khi đổi trạng thái**

Trong `app/Actions/Task/TransitionTaskStatusAction.php`, thêm import `use App\Enums\TaskActivityType;`, thêm constructor:

```php
    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}
```

rồi trong closure của `DB::transaction`, ngay sau khối `TaskStatusHistory::create([...]);`:

```php
            $this->recordActivity->execute($actor, $lockedTask, TaskActivityType::StatusChanged, [
                'from' => $fromStatus->value,
                'to' => $targetStatus->value,
            ]);
```

- [ ] **Step 5: Chạy test để xác nhận pass**

Run: `php artisan test tests/Feature/Task/TaskActivityTest.php`
Expected: PASS, 7 test.

- [ ] **Step 6: Chạy toàn bộ test công việc để chắc không hỏng gì**

Run: `php artisan test tests/Feature/Task`
Expected: PASS toàn bộ.

- [ ] **Step 7: Commit**

```bash
./vendor/bin/pint app/Actions/Task/
git add app/Actions/Task/CreateTaskAction.php app/Actions/Task/TransitionTaskStatusAction.php tests/Feature/Task/TaskActivityTest.php
git commit -m "feat(task): record activities for task creation and status changes"
```

---

### Task 3: Ghi hoạt động phân công và tiến độ

**Files:**
- Modify: `app/Actions/Task/UpdateTaskAction.php` (đổi chữ ký `execute`)
- Modify: `app/Actions/Task/UpdateTaskProgressAction.php` (đổi chữ ký `execute`)
- Modify: `app/Http/Controllers/TaskController.php` (method `update` và `updateProgress` — truyền thêm actor)
- Test: `tests/Feature/Task/TaskActivityTest.php` (thêm test vào cuối file)

**Interfaces:**
- Consumes: `RecordTaskActivityAction` (Task 1).
- Produces:
  - `UpdateTaskAction::execute(User $actor, Task $task, array $data): Task` — **chữ ký mới**, thêm tham số đầu tiên.
  - `UpdateTaskProgressAction::execute(User $actor, Task $task, int $progress): Task` — **chữ ký mới**, thêm tham số đầu tiên.
  - Hoạt động `assigned` với payload `from_assignee_id`, `from_assignee_name`, `to_assignee_id`, `to_assignee_name` (đều nullable).
  - Hoạt động `progress_updated` với payload `from`, `to` (số nguyên).

- [ ] **Step 1: Viết test thất bại**

Thêm vào cuối `tests/Feature/Task/TaskActivityTest.php`:

```php
test('changing the assignee records an activity with both names', function () {
    $actor = User::factory()->create();
    $previous = User::factory()->create(['name' => 'Lan Nguyễn']);
    $next = User::factory()->create(['name' => 'Hùng Trần']);
    $task = Task::factory()->create(['assignee_id' => $previous->id]);

    app(App\Actions\Task\UpdateTaskAction::class)->execute($actor, $task, [
        'assignee_id' => $next->id,
    ]);

    $activity = TaskActivity::query()->where('type', 'assigned')->sole();

    expect($activity->actor_id)->toBe($actor->id)
        ->and($activity->payload)->toBe([
            'from_assignee_id' => $previous->id,
            'from_assignee_name' => 'Lan Nguyễn',
            'to_assignee_id' => $next->id,
            'to_assignee_name' => 'Hùng Trần',
        ]);
});

test('clearing the assignee records an activity with a null target', function () {
    $previous = User::factory()->create(['name' => 'Lan Nguyễn']);
    $task = Task::factory()->create(['assignee_id' => $previous->id]);

    app(App\Actions\Task\UpdateTaskAction::class)->execute(User::factory()->create(), $task, [
        'assignee_id' => null,
    ]);

    $activity = TaskActivity::query()->where('type', 'assigned')->sole();

    expect($activity->payload['to_assignee_id'])->toBeNull()
        ->and($activity->payload['to_assignee_name'])->toBeNull()
        ->and($activity->payload['from_assignee_name'])->toBe('Lan Nguyễn');
});

test('updating a task without touching the assignee records no assignment activity', function () {
    $assignee = User::factory()->create();
    $task = Task::factory()->create(['assignee_id' => $assignee->id, 'title' => 'Cũ']);

    app(App\Actions\Task\UpdateTaskAction::class)->execute(User::factory()->create(), $task, [
        'title' => 'Mới',
        'assignee_id' => $assignee->id,
    ]);

    expect(TaskActivity::query()->where('type', 'assigned')->count())->toBe(0)
        ->and($task->fresh()->title)->toBe('Mới');
});

test('updating the progress records an activity with the previous value', function () {
    $actor = User::factory()->create();
    $task = Task::factory()->create([
        'status' => App\Enums\TaskStatus::InProgress->value,
        'progress' => 40,
    ]);

    app(App\Actions\Task\UpdateTaskProgressAction::class)->execute($actor, $task, 65);

    $activity = TaskActivity::query()->where('type', 'progress_updated')->sole();

    expect($activity->actor_id)->toBe($actor->id)
        ->and($activity->payload)->toBe(['from' => 40, 'to' => 65])
        ->and($task->fresh()->progress)->toBe(65);
});

test('re-submitting the same progress records no activity', function () {
    $task = Task::factory()->create([
        'status' => App\Enums\TaskStatus::InProgress->value,
        'progress' => 40,
    ]);

    app(App\Actions\Task\UpdateTaskProgressAction::class)->execute(User::factory()->create(), $task, 40);

    expect(TaskActivity::query()->where('type', 'progress_updated')->count())->toBe(0);
});
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run: `php artisan test tests/Feature/Task/TaskActivityTest.php --filter="assignee"`
Expected: FAIL với `ArgumentCountError` hoặc lỗi kiểu tham số — `UpdateTaskAction::execute` chưa nhận `User`.

- [ ] **Step 3: Sửa UpdateTaskAction**

Thay toàn bộ `app/Actions/Task/UpdateTaskAction.php` bằng:

```php
<?php

namespace App\Actions\Task;

use App\Enums\TaskActivityType;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskDescriptionSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateTaskAction
{
    public function __construct(
        private readonly TaskDescriptionSanitizer $descriptionSanitizer,
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Task $task, array $data): Task
    {
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $this->guardAgainstCircularReference($task, (int) $data['parent_id']);
        }

        $data['description'] = $this->descriptionSanitizer->sanitize($data['description'] ?? null);

        return DB::transaction(function () use ($actor, $task, $data): Task {
            $previousAssigneeId = $task->assignee_id;

            $task->update($data);

            if (array_key_exists('assignee_id', $data)) {
                $newAssigneeId = $data['assignee_id'] === null ? null : (int) $data['assignee_id'];

                if ($newAssigneeId !== $previousAssigneeId) {
                    $this->recordActivity->execute($actor, $task, TaskActivityType::Assigned, [
                        'from_assignee_id' => $previousAssigneeId,
                        'from_assignee_name' => $this->userName($previousAssigneeId),
                        'to_assignee_id' => $newAssigneeId,
                        'to_assignee_name' => $this->userName($newAssigneeId),
                    ]);
                }
            }

            return $task->refresh();
        });
    }

    private function userName(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        return User::withTrashed()->whereKey($userId)->value('name');
    }

    private function guardAgainstCircularReference(Task $task, int $parentId): void
    {
        $ancestor = Task::find($parentId);

        while ($ancestor !== null) {
            if ($ancestor->is($task)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Không thể chọn một công việc con làm công việc cha.',
                ]);
            }

            $ancestor = $ancestor->parent;
        }
    }
}
```

Tên người phụ trách đọc bằng truy vấn riêng (`value('name')`) thay vì qua quan hệ `$task->assignee` — quan hệ đã nạp trước đó sẽ giữ dữ liệu cũ sau khi `update()`, dễ ghi nhầm tên.

- [ ] **Step 4: Sửa UpdateTaskProgressAction**

Thay toàn bộ `app/Actions/Task/UpdateTaskProgressAction.php` bằng:

```php
<?php

namespace App\Actions\Task;

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateTaskProgressAction
{
    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    public function execute(User $actor, Task $task, int $progress): Task
    {
        return DB::transaction(function () use ($actor, $task, $progress): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

            if ($lockedTask->status !== TaskStatus::InProgress) {
                throw ValidationException::withMessages([
                    'progress' => 'Chỉ có thể cập nhật tiến độ khi công việc đang được thực hiện.',
                ]);
            }

            $previousProgress = $lockedTask->progress;

            if ($previousProgress === $progress) {
                return $lockedTask;
            }

            $lockedTask->update(['progress' => $progress]);

            $this->recordActivity->execute($actor, $lockedTask, TaskActivityType::ProgressUpdated, [
                'from' => $previousProgress,
                'to' => $progress,
            ]);

            return $lockedTask->refresh();
        });
    }
}
```

- [ ] **Step 5: Cập nhật controller cho khớp chữ ký mới**

Trong `app/Http/Controllers/TaskController.php`:

Method `update` — đổi dòng gọi Action thành:

```php
        $action->execute($request->user(), $task, $request->validated());
```

Method `updateProgress` — đổi dòng gọi Action thành:

```php
        $action->execute($request->user(), $task, $request->integer('progress'));
```

- [ ] **Step 6: Chạy test để xác nhận pass**

Run: `php artisan test tests/Feature/Task/TaskActivityTest.php`
Expected: PASS, 12 test.

- [ ] **Step 7: Chạy toàn bộ suite — chữ ký Action đã đổi**

Run: `php artisan test`
Expected: PASS toàn bộ. Nếu có test cũ gọi `UpdateTaskAction::execute($task, ...)` hoặc `UpdateTaskProgressAction::execute($task, ...)` theo chữ ký cũ, cập nhật lời gọi ở test đó cho khớp — **không** khôi phục chữ ký cũ.

- [ ] **Step 8: Commit**

```bash
./vendor/bin/pint app/
git add app/Actions/Task/UpdateTaskAction.php app/Actions/Task/UpdateTaskProgressAction.php app/Http/Controllers/TaskController.php tests/
git commit -m "feat(task): record assignment and progress activities"
```

---

### Task 4: Ghi hoạt động bình luận và tệp đính kèm

**Files:**
- Modify: `app/Actions/Task/CreateTaskCommentAction.php` (bọc transaction, ghi hoạt động)
- Modify: `app/Actions/Task/StoreTaskAttachmentAction.php` (ghi hoạt động trong transaction sẵn có)
- Modify: `app/Actions/Task/DeleteTaskAttachmentAction.php` (ghi hoạt động trong transaction sẵn có)
- Test: `tests/Feature/Task/TaskActivityTest.php` (thêm test vào cuối file)

**Interfaces:**
- Consumes: `RecordTaskActivityAction` (Task 1).
- Produces:
  - Hoạt động `commented` với payload `comment_id`, `excerpt` (tối đa 120 ký tự).
  - Hoạt động `attachment_added` với payload `attachment_ids` (mảng), `original_names` (mảng), `file_count` — **một** hoạt động cho mỗi request upload.
  - Hoạt động `attachment_removed` với payload `attachment_id`, `original_name`.

- [ ] **Step 1: Viết test thất bại**

Thêm vào cuối `tests/Feature/Task/TaskActivityTest.php`:

```php
test('commenting records an activity with a trimmed excerpt', function () {
    $author = User::factory()->create();
    $task = Task::factory()->create();
    $body = str_repeat('Nội dung trao đổi rất dài. ', 20);

    $comment = app(App\Actions\Task\CreateTaskCommentAction::class)->execute($author, $task, $body);

    $activity = TaskActivity::query()->where('type', 'commented')->sole();

    expect($activity->actor_id)->toBe($author->id)
        ->and($activity->payload['comment_id'])->toBe($comment->id)
        ->and(mb_strlen($activity->payload['excerpt']))->toBeLessThanOrEqual(121);
});

test('uploading several files records a single activity for the request', function () {
    Illuminate\Support\Facades\Storage::fake('local');

    $uploader = userWithPermissions([
        App\Enums\PermissionName::TaskView->value,
        App\Enums\PermissionName::TaskComment->value,
    ]);
    $task = Task::factory()->create();

    $this->actingAs($uploader)->post(route('tasks.attachments.store', $task), [
        'files' => [
            Illuminate\Http\UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            Illuminate\Http\UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
            Illuminate\Http\UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
        ],
    ])->assertRedirect(route('tasks.show', $task));

    $activity = TaskActivity::query()->where('type', 'attachment_added')->sole();

    expect($activity->payload['file_count'])->toBe(3)
        ->and($activity->payload['original_names'])->toBe(['a.pdf', 'b.pdf', 'c.pdf'])
        ->and($activity->payload['attachment_ids'])->toHaveCount(3);
});

test('deleting an attachment records an activity that survives the soft delete', function () {
    Illuminate\Support\Facades\Storage::fake('local');

    $uploader = userWithPermissions([
        App\Enums\PermissionName::TaskView->value,
        App\Enums\PermissionName::TaskComment->value,
    ]);
    $task = Task::factory()->create();
    $attachment = App\Models\TaskAttachment::factory()->for($task)->for($uploader, 'uploader')->create([
        'original_name' => 'báo cáo quý.pdf',
    ]);

    $this->actingAs($uploader)
        ->delete(route('tasks.attachments.destroy', [$task, $attachment]))
        ->assertRedirect(route('tasks.show', $task));

    $activity = TaskActivity::query()->where('type', 'attachment_removed')->sole();

    expect($activity->payload['attachment_id'])->toBe($attachment->id)
        ->and($activity->payload['original_name'])->toBe('báo cáo quý.pdf')
        ->and(App\Models\TaskAttachment::query()->count())->toBe(0);
});

test('a failed upload leaves no orphaned activity', function () {
    Illuminate\Support\Facades\Storage::fake('local');

    $uploader = userWithPermissions([
        App\Enums\PermissionName::TaskView->value,
        App\Enums\PermissionName::TaskComment->value,
    ]);
    $task = Task::factory()->create();

    $this->actingAs($uploader)->post(route('tasks.attachments.store', $task), [
        'files' => [Illuminate\Http\UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
    ])->assertSessionHasErrors('files.0');

    expect(TaskActivity::query()->where('type', 'attachment_added')->count())->toBe(0);
});
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run: `php artisan test tests/Feature/Task/TaskActivityTest.php --filter="commenting records"`
Expected: FAIL — `sole()` không tìm thấy bản ghi `commented`.

- [ ] **Step 3: Ghi hoạt động khi bình luận**

Thay toàn bộ `app/Actions/Task/CreateTaskCommentAction.php` bằng:

```php
<?php

namespace App\Actions\Task;

use App\Enums\TaskActivityType;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateTaskCommentAction
{
    private const EXCERPT_LENGTH = 120;

    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    public function execute(User $actor, Task $task, string $body): TaskComment
    {
        return DB::transaction(function () use ($actor, $task, $body): TaskComment {
            $comment = $task->comments()->create([
                'author_id' => $actor->id,
                'body' => $body,
            ]);

            $this->recordActivity->execute($actor, $task, TaskActivityType::Commented, [
                'comment_id' => $comment->id,
                'excerpt' => Str::limit($body, self::EXCERPT_LENGTH, '…'),
            ]);

            return $comment;
        });
    }
}
```

- [ ] **Step 4: Ghi hoạt động khi tải tệp lên**

Trong `app/Actions/Task/StoreTaskAttachmentAction.php`:

Thêm import `use App\Enums\TaskActivityType;`, và thêm `RecordTaskActivityAction` vào constructor bên cạnh `AuditLogger`:

```php
    public function __construct(
        private AuditLogger $auditLogger,
        private RecordTaskActivityAction $recordActivity,
    ) {}
```

Trong closure của `DB::transaction`, sửa vòng lặp tạo bản ghi để giữ lại các model vừa tạo, rồi ghi hoạt động sau khi ghi audit:

```php
                $attachments = [];

                foreach ($rows as $row) {
                    $attachments[] = $task->attachments()->create($row);
                }

                $this->auditLogger->record(
                    actor: $actor,
                    action: AuditAction::TaskAttachmentUploaded,
                    subject: $task,
                    metadata: [
                        'file_count' => count($rows),
                        'total_size_bytes' => array_sum(array_column($rows, 'size_bytes')),
                        'original_names' => array_column($rows, 'original_name'),
                    ],
                );

                $this->recordActivity->execute($actor, $task, TaskActivityType::AttachmentAdded, [
                    'attachment_ids' => array_map(static fn ($attachment): int => $attachment->id, $attachments),
                    'original_names' => array_column($rows, 'original_name'),
                    'file_count' => count($rows),
                ]);
```

- [ ] **Step 5: Ghi hoạt động khi xoá tệp**

Trong `app/Actions/Task/DeleteTaskAttachmentAction.php`, thêm import `use App\Enums\TaskActivityType;`, thêm `RecordTaskActivityAction` vào constructor:

```php
    public function __construct(
        private AuditLogger $auditLogger,
        private RecordTaskActivityAction $recordActivity,
    ) {}
```

rồi trong closure của `DB::transaction`, ngay trước `$attachment->delete();`:

```php
            $this->recordActivity->execute($actor, $attachment->task, TaskActivityType::AttachmentRemoved, [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
            ]);
```

- [ ] **Step 6: Chạy test để xác nhận pass**

Run: `php artisan test tests/Feature/Task/TaskActivityTest.php`
Expected: PASS, 16 test.

- [ ] **Step 7: Chạy toàn bộ suite**

Run: `php artisan test`
Expected: PASS toàn bộ.

- [ ] **Step 8: Commit**

```bash
./vendor/bin/pint app/Actions/Task/
git add app/Actions/Task/CreateTaskCommentAction.php app/Actions/Task/StoreTaskAttachmentAction.php app/Actions/Task/DeleteTaskAttachmentAction.php tests/Feature/Task/TaskActivityTest.php
git commit -m "feat(task): record comment and attachment activities"
```

---

### Task 5: Đưa timeline ra trang chi tiết

**Files:**
- Modify: `app/Http/Controllers/TaskController.php` (method `show`)
- Test: `tests/Feature/Task/TaskActivityTest.php` (thêm test vào cuối file)

**Interfaces:**
- Consumes: `Task::activities()` (Task 1) và các hoạt động do Task 2–4 ghi.
- Produces: prop Inertia `activities` — paginator 30 dòng mỗi trang, `pageName: 'activities_page'`, mỗi phần tử gồm `id`, `type`, `payload`, `created_at`, `actor` (`{id, name, avatar_url}` hoặc `null`).

- [ ] **Step 1: Viết test thất bại**

Thêm vào cuối `tests/Feature/Task/TaskActivityTest.php`:

```php
test('task details expose paginated activities newest first', function () {
    $viewer = userWithPermissions([App\Enums\PermissionName::TaskView->value]);
    $task = Task::factory()->create();

    TaskActivity::factory()->count(31)->for($task)->create();
    $newest = TaskActivity::query()->where('task_id', $task->id)->orderByDesc('id')->first();

    $this->actingAs($viewer)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('activities.data', 30)
            ->where('activities.total', 31)
            ->where('activities.data.0.id', $newest->id)
            ->has('activities.data.0.type')
            ->has('activities.data.0.created_at'));
});

test('the timeline keeps activities whose actor was soft deleted', function () {
    $viewer = userWithPermissions([App\Enums\PermissionName::TaskView->value]);
    $task = Task::factory()->create();
    $actor = User::factory()->create(['name' => 'Người đã nghỉ']);

    TaskActivity::factory()->for($task)->for($actor, 'actor')->create();
    $actor->delete();

    $this->actingAs($viewer)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('activities.data.0.actor.name', 'Người đã nghỉ'));
});

test('the timeline does not issue one query per actor', function () {
    $viewer = userWithPermissions([App\Enums\PermissionName::TaskView->value]);
    $task = Task::factory()->create();

    foreach (range(1, 10) as $index) {
        TaskActivity::factory()->for($task)->for(User::factory()->create(), 'actor')->create();
    }

    $queries = 0;
    Illuminate\Support\Facades\DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->actingAs($viewer)->get(route('tasks.show', $task))->assertOk();

    // Eager load: 10 actor khác nhau vẫn chỉ tốn một truy vấn cho quan hệ actor.
    // Ngưỡng 30 rộng rãi so với số truy vấn cố định của trang; nếu N+1 quay lại,
    // con số sẽ vượt xa ngưỡng này.
    expect($queries)->toBeLessThan(30);
});

test('the task page no longer sends status histories', function () {
    $viewer = userWithPermissions([App\Enums\PermissionName::TaskView->value]);
    $task = Task::factory()->create();

    $this->actingAs($viewer)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->missing('task.status_histories'));
});
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run: `php artisan test tests/Feature/Task/TaskActivityTest.php --filter="paginated activities"`
Expected: FAIL — prop `activities` không tồn tại.

- [ ] **Step 3: Bổ sung prop và gỡ status histories**

Trong `app/Http/Controllers/TaskController.php`, method `show()`:

Trong mảng truyền cho `$task->load([...])`, **xoá** dòng:

```php
            'statusHistories.actor:id,name,avatar_path',
```

Ngay sau khối `$attachments = ...->all();`, thêm:

```php
        $activities = $task->activities()
            ->with('actor:id,name,avatar_path')
            ->paginate(
                perPage: 30,
                columns: ['id', 'task_id', 'actor_id', 'type', 'payload', 'created_at'],
                pageName: 'activities_page',
            )
            ->withQueryString();
```

Trong mảng trả về của `Inertia::render('Tasks/Show', [...])`, thêm ngay sau `'attachments' => $attachments,`:

```php
            'activities' => $activities,
```

- [ ] **Step 4: Chạy test để xác nhận pass**

Run: `php artisan test tests/Feature/Task/TaskActivityTest.php`
Expected: PASS, 20 test.

- [ ] **Step 5: Chạy toàn bộ suite**

Run: `php artisan test`
Expected: PASS toàn bộ. Nếu `TaskControllerTest` có assertion dựa vào `task.status_histories`, sửa assertion đó sang `activities` — **không** khôi phục việc nạp `statusHistories`.

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint app/Http/Controllers/TaskController.php
git add app/Http/Controllers/TaskController.php tests/
git commit -m "feat(task): expose the activity timeline on the task detail page"
```

---

### Task 6: Giao diện timeline

**Files:**
- Create: `resources/js/Components/TaskActivityTimeline.vue`
- Modify: `resources/js/types/index.d.ts` (thêm interface `TaskActivity`)
- Modify: `resources/js/Pages/Tasks/Show.vue` (thay mục "Lịch sử trạng thái" bằng component mới)

**Interfaces:**
- Consumes: prop `activities` (Task 5); hằng `taskStatusLabels` trong `@/Constants/task`.
- Produces: component `TaskActivityTimeline` nhận prop `{ activities: PaginatedActivities }`.

- [ ] **Step 1: Thêm kiểu TypeScript**

Trong `resources/js/types/index.d.ts`, ngay sau interface `TaskAttachment`:

```ts
export type TaskActivityType =
    | 'created'
    | 'status_changed'
    | 'assigned'
    | 'progress_updated'
    | 'commented'
    | 'attachment_added'
    | 'attachment_removed';

export interface TaskActivity {
    id: number;
    type: TaskActivityType;
    payload: Record<string, string | number | null | string[] | number[]> | null;
    created_at: string;
    actor?: Pick<User, 'id' | 'name' | 'avatar_url'> | null;
}
```

- [ ] **Step 2: Viết component**

Tạo `resources/js/Components/TaskActivityTimeline.vue`:

```vue
<script setup lang="ts">
import AppIcon from '@/Components/AppIcon.vue';
import AppUserAvatar from '@/Components/AppUserAvatar.vue';
import { taskStatusLabels } from '@/Constants/task';
import { Link } from '@inertiajs/vue3';
import type { TaskActivity, TaskActivityType, TaskStatus } from '@/types';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    activities: {
        data: TaskActivity[];
        current_page: number;
        last_page: number;
        total: number;
        links: PaginationLink[];
    };
}>();

const KNOWN_TYPES: TaskActivityType[] = [
    'created',
    'status_changed',
    'assigned',
    'progress_updated',
    'commented',
    'attachment_added',
    'attachment_removed',
];

const iconFor: Record<TaskActivityType, string> = {
    created: 'plus',
    status_changed: 'arrow-right',
    assigned: 'user',
    progress_updated: 'check',
    commented: 'message',
    attachment_added: 'folder',
    attachment_removed: 'trash',
};

const toneFor: Record<TaskActivityType, string> = {
    created: 'bg-slate-100 text-slate-600',
    status_changed: 'bg-brand-50 text-brand-700',
    assigned: 'bg-blue-50 text-blue-700',
    progress_updated: 'bg-amber-50 text-amber-700',
    commented: 'bg-violet-50 text-violet-700',
    attachment_added: 'bg-emerald-50 text-emerald-700',
    attachment_removed: 'bg-red-50 text-red-700',
};

const isKnown = (activity: TaskActivity) => KNOWN_TYPES.includes(activity.type);

const actorName = (activity: TaskActivity) => activity.actor?.name ?? 'Tài khoản đã xóa';

const statusLabel = (value: unknown) =>
    typeof value === 'string' ? (taskStatusLabels[value as TaskStatus] ?? value) : '';

const describe = (activity: TaskActivity): string => {
    const payload = activity.payload ?? {};

    switch (activity.type) {
        case 'created':
            return 'đã tạo công việc';
        case 'status_changed':
            return `chuyển trạng thái từ ${statusLabel(payload.from)} sang ${statusLabel(payload.to)}`;
        case 'assigned': {
            const from = payload.from_assignee_name as string | null;
            const to = payload.to_assignee_name as string | null;

            if (to === null) {
                return `bỏ phân công ${from ?? 'người phụ trách'}`;
            }

            return from === null ? `giao việc cho ${to}` : `chuyển phụ trách từ ${from} sang ${to}`;
        }
        case 'progress_updated':
            return `cập nhật tiến độ từ ${payload.from}% lên ${payload.to}%`;
        case 'commented':
            return `đã trao đổi: ${payload.excerpt as string}`;
        case 'attachment_added': {
            const names = (payload.original_names as string[] | undefined) ?? [];

            return `đính kèm ${payload.file_count} tệp: ${names.join(', ')}`;
        }
        case 'attachment_removed':
            return `xoá tệp ${payload.original_name as string}`;
        default:
            return '';
    }
};

const formatDateTime = (value: string) =>
    new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' });
</script>

<template>
    <section class="app-panel overflow-hidden">
        <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
            <h2 class="font-display text-base font-bold text-ink-950">Dòng thời gian</h2>
            <p class="mt-1 text-xs text-slate-500">
                {{ activities.total }} hoạt động đã được ghi nhận trong công việc này.
            </p>
        </div>

        <ol v-if="activities.data.length" class="divide-y divide-slate-100">
            <li
                v-for="activity in activities.data.filter(isKnown)"
                :key="activity.id"
                class="flex gap-4 px-5 py-4 sm:px-6"
            >
                <span
                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
                    :class="toneFor[activity.type]"
                >
                    <AppIcon :name="iconFor[activity.type]" class="size-4" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-sm text-slate-700">
                        <span class="font-semibold text-slate-900">{{ actorName(activity) }}</span>
                        {{ ' ' }}{{ describe(activity) }}
                    </p>
                    <div class="mt-1 flex items-center gap-2">
                        <AppUserAvatar
                            :name="actorName(activity)"
                            :avatar-url="activity.actor?.avatar_url"
                            class="size-5"
                        />
                        <time class="text-xs text-slate-400" :datetime="activity.created_at">
                            {{ formatDateTime(activity.created_at) }}
                        </time>
                    </div>
                </div>
            </li>
        </ol>

        <p v-else class="px-5 py-10 text-center text-sm text-slate-400">Chưa có hoạt động nào.</p>

        <nav
            v-if="activities.last_page > 1"
            class="flex flex-wrap items-center justify-center gap-1 border-t border-slate-100 px-5 py-3"
            aria-label="Phân trang hoạt động"
        >
            <template v-for="link in activities.links" :key="link.label">
                <span
                    v-if="!link.url"
                    class="rounded-lg px-3 py-1.5 text-xs text-slate-300"
                    v-text="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    class="rounded-lg px-3 py-1.5 text-xs"
                    :class="link.active ? 'bg-brand-600 text-white' : 'text-slate-600 hover:bg-slate-100'"
                    v-text="link.label"
                />
            </template>
        </nav>
    </section>
</template>
```

Ghi chú: `v-text` dùng cho nhãn phân trang vì Laravel trả về ký tự HTML (`&laquo;`, `&raquo;`) — `v-text` hiển thị nguyên văn thay vì diễn giải HTML, an toàn hơn `v-html`.

- [ ] **Step 3: Thay mục lịch sử trạng thái trong trang chi tiết**

Trong `resources/js/Pages/Tasks/Show.vue`:

1. Thêm import: `import TaskActivityTimeline from '@/Components/TaskActivityTimeline.vue';`
2. Sửa dòng import type thành: `import type { PageProps, Task, TaskActivity, TaskAttachment, TaskComment } from '@/types';`
3. Thêm interface phân trang cạnh `PaginatedComments`:

```ts
interface PaginatedActivities {
    data: TaskActivity[];
    current_page: number;
    last_page: number;
    total: number;
    links: PaginationLink[];
}
```

4. Trong `defineProps`, thêm `activities: PaginatedActivities;` sau `attachments: TaskAttachment[];`
5. Xoá toàn bộ khối `<section>` chứa tiêu đề "Lịch sử trạng thái" (từ thẻ `<section class="app-panel overflow-hidden">` mở đầu bằng `<h2 ...>Lịch sử trạng thái</h2>` cho tới thẻ `</section>` đóng của nó), thay bằng:

```vue
                <TaskActivityTimeline class="mt-6" :activities="activities" />
```

6. Nếu sau khi xoá mà `taskStatusLabels` hoặc `AppIcon` không còn nơi dùng trong `Show.vue`, gỡ luôn import không dùng — ESLint sẽ báo. Kiểm tra kỹ trước khi gỡ vì `taskStatusLabels` còn dùng ở phần badge trạng thái đầu trang.

- [ ] **Step 4: Kiểm tra lint và build**

Run: `npm run lint`
Expected: không lỗi.

Run: `npm run build`
Expected: build thành công, không lỗi TypeScript.

- [ ] **Step 5: Rà lại theo checklist giao diện**

Đọc lại code vừa viết và xác nhận từng mục, ghi nhận xét vào báo cáo:

1. Mỗi loại trong bảy loại hoạt động đều có icon, màu và câu mô tả tiếng Việt riêng.
2. Loại lạ không nằm trong `KNOWN_TYPES` bị bỏ qua, không làm vỡ giao diện.
3. Có empty state khi chưa có hoạt động nào.
4. Phân trang chỉ hiện khi có nhiều hơn một trang, dùng `preserve-scroll`.
5. Tên người lấy từ `payload` (phân công) và từ `actor` (người thực hiện), có fallback "Tài khoản đã xóa".
6. Trên màn hình hẹp, câu mô tả xuống dòng được, không tràn ngang (`min-w-0` trên khối nội dung).

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/TaskActivityTimeline.vue resources/js/Pages/Tasks/Show.vue resources/js/types/index.d.ts
git commit -m "feat(task): add the unified activity timeline to the task page"
```

---

### Task 7: Cập nhật tài liệu và đóng Sprint 2

**Files:**
- Modify: `docs/srs/SRS.md`
- Modify: `context/sprint-plan.md`
- Regenerate: `docs/srs/DORMIDA-WORK-SRS.pdf`

**Interfaces:**
- Consumes: toàn bộ kết quả Task 1–6.
- Produces: tài liệu khớp code, Sprint 2 được đánh dấu hoàn tất.

- [ ] **Step 1: Cập nhật SRS**

Trong `docs/srs/SRS.md`:

1. Mục 3.6, dòng FR-TASK-11: đổi cột trạng thái từ `Đang triển khai` thành `Đã triển khai`.
2. Mục 10.4, thêm một dòng vào bảng phụ trợ:

```markdown
| `task_activities` | Dòng thời gian hoạt động của công việc: loại sự kiện, người thực hiện, payload JSON — append-only |
```

3. Bảng đầu file: đổi ô "Trạng thái dự án" thành `Sprint 2 (Task Core) hoàn tất — chuẩn bị Sprint 3 (Project)`, phiên bản tài liệu `1.2`, ngày phát hành `29/07/2026`.
4. Mục 11: đổi trạng thái Sprint 2 trong bảng lộ trình thành `Hoàn tất`, và sửa đoạn dưới bảng thành: Sprint 2 đã hoàn tất toàn bộ hạng mục gồm tệp đính kèm và activity timeline; công việc kế tiếp là Sprint 3 — Project.
5. Mục 14, thêm dòng lịch sử phiên bản:

```markdown
| 1.2 | 29/07/2026 | Hoàn tất Sprint 2: cập nhật FR-TASK-11 (activity timeline) sang trạng thái đã triển khai |
```

- [ ] **Step 2: Cập nhật sprint-plan**

Trong `context/sprint-plan.md`, phần "Trạng thái hiện tại": đổi dòng Sprint 2 thành đã hoàn tất (liệt kê đủ Task CRUD, phân công, filter, phân trang, comment, status flow có history, attachment, activity timeline), và đổi "Công việc kế tiếp" thành Sprint 3 — Project.

- [ ] **Step 3: Render lại PDF**

Run: `node docs/srs/build-pdf.mjs`
Expected: in ra đường dẫn `docs/srs/DORMIDA-WORK-SRS.pdf`, không lỗi.

- [ ] **Step 4: Chạy lại toàn bộ kiểm tra CI**

```bash
./vendor/bin/pint --test
npm run lint
npm run build
php artisan test
```

Expected: tất cả pass. Nếu `pint --test` báo lỗi định dạng, chạy `./vendor/bin/pint` rồi đưa cả phần format vào commit này.

- [ ] **Step 5: Commit**

```bash
git add docs/srs/SRS.md docs/srs/DORMIDA-WORK-SRS.pdf context/sprint-plan.md
git commit -m "docs(task): record the activity timeline and close sprint 2"
```

---

## Ghi chú triển khai

**Chữ ký Action đổi ở Task 3:** `UpdateTaskAction::execute` và `UpdateTaskProgressAction::execute` đều nhận thêm `User $actor` làm tham số đầu tiên. Chỉ `TaskController` gọi hai Action này trong code production, nhưng test cũ có thể gọi trực tiếp — chạy toàn bộ suite ở Task 3 Step 7 để bắt hết.

**Thứ tự sắp xếp:** timeline sắp theo `id` giảm dần chứ không phải `created_at`, để thứ tự ổn định khi nhiều hoạt động rơi vào cùng một giây (upload nhiều tệp, hoặc đổi trạng thái kèm ghi hoạt động trong cùng transaction).

**Không nằm trong kế hoạch này** (lý do ở mục 10 của spec): ghi nhận sửa tiêu đề/mô tả/thời hạn/độ ưu tiên, bỏ hoặc migrate `task_status_histories`, lọc timeline theo loại hoặc người, realtime, bảng tin hoạt động toàn hệ thống.
