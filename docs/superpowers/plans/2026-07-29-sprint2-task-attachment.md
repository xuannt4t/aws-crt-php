# Task Attachment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cho phép đính kèm tệp vào công việc ở trang chi tiết, tải xuống có kiểm soát quyền và xoá mềm có ghi audit (FR-TASK-10).

**Architecture:** Bảng `task_attachments` gắn trực tiếp vào `tasks`. Tệp lưu trên disk `local` (ngoài `public/`), mọi lượt tải đi qua route riêng có Policy kiểm tra rồi `Storage::download()`. Controller riêng `TaskAttachmentController` + Action, theo đúng pattern `TaskCommentController` đang có.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL 8, Pest, Vue 3 + Inertia + TypeScript, Tailwind, PrimeVue.

**Spec:** `docs/superpowers/specs/2026-07-29-sprint2-task-attachment-design.md`

## Global Constraints

- Luồng bắt buộc: `Route → Controller → FormRequest → Action → Model → Inertia Response`. Controller không chứa query phức tạp hay business rule.
- Mọi class mới khai báo `final`, có type hint và return type, tuân thủ PSR-12 (`./vendor/bin/pint`).
- Không thêm permission mới ngoài `context/permission-matrix.md`. Chỉ dùng `task.view`, `task.comment`, `task.update`.
- Giới hạn tệp: tối đa 5 tệp mỗi request, mỗi tệp tối đa 10MB (`max:10240` KB).
- Whitelist MIME (dùng rule `mimetypes`, không dùng `mimes`): `application/pdf`, `application/msword`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, `application/vnd.ms-excel`, `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, `application/vnd.ms-powerpoint`, `application/vnd.openxmlformats-officedocument.presentationml.presentation`, `text/csv`, `text/plain`, `application/zip`, `image/jpeg`, `image/png`, `image/webp`.
- Disk lưu trữ: `local`, thư mục `task-attachments/{task_id}`.
- Mọi chuỗi hiển thị cho người dùng bằng tiếng Việt.
- Test framework: Pest. Chạy một file: `php artisan test tests/Feature/Task/TaskAttachmentControllerTest.php`. Chạy một test: thêm `--filter="tên test"`.
- Mỗi task kết thúc bằng một commit riêng. Không dùng `--no-verify`.

---

### Task 1: Bảng, model và factory

**Files:**
- Create: `database/migrations/2026_07_29_090000_create_task_attachments_table.php`
- Create: `app/Models/TaskAttachment.php`
- Create: `database/factories/TaskAttachmentFactory.php`
- Modify: `app/Models/Task.php` (thêm quan hệ `attachments()` cạnh `comments()`)
- Test: `tests/Feature/Task/TaskAttachmentModelTest.php`

**Interfaces:**
- Consumes: `App\Models\Task`, `App\Models\User` (đã có).
- Produces: `TaskAttachment` với `$fillable = ['task_id','uploader_id','disk','path','original_name','mime_type','size_bytes']`, quan hệ `task(): BelongsTo`, `uploader(): BelongsTo`, accessor `size_for_humans: string`; `Task::attachments(): HasMany` sắp xếp `latest('id')`.

- [ ] **Step 1: Viết test thất bại**

Tạo `tests/Feature/Task/TaskAttachmentModelTest.php`:

```php
<?php

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;

test('a task exposes its attachments newest first', function () {
    $task = Task::factory()->create();

    $older = TaskAttachment::factory()->for($task)->create();
    $newer = TaskAttachment::factory()->for($task)->create();

    expect($task->attachments()->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

test('an attachment keeps its uploader even after the account is soft deleted', function () {
    $uploader = User::factory()->create();
    $attachment = TaskAttachment::factory()->for($uploader, 'uploader')->create();

    $uploader->delete();

    expect($attachment->fresh()->uploader->id)->toBe($uploader->id);
});

test('an attachment formats its size for humans', function (int $bytes, string $expected) {
    $attachment = TaskAttachment::factory()->create(['size_bytes' => $bytes]);

    expect($attachment->size_for_humans)->toBe($expected);
})->with([
    'bytes' => [512, '512 B'],
    'kilobytes' => [2048, '2.0 KB'],
    'megabytes' => [5 * 1024 * 1024, '5.0 MB'],
]);

test('soft deleting an attachment keeps the row in the database', function () {
    $attachment = TaskAttachment::factory()->create();

    $attachment->delete();

    expect(TaskAttachment::withTrashed()->count())->toBe(1)
        ->and(TaskAttachment::count())->toBe(0);
});
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run: `php artisan test tests/Feature/Task/TaskAttachmentModelTest.php`
Expected: FAIL với `Class "App\Models\TaskAttachment" not found`.

- [ ] **Step 3: Viết migration**

Tạo `database/migrations/2026_07_29_090000_create_task_attachments_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $table->string('disk', 30);
            $table->string('path', 2048);
            $table->string('original_name');
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
    }
};
```

- [ ] **Step 4: Viết model**

Tạo `app/Models/TaskAttachment.php`:

```php
<?php

namespace App\Models;

use Database\Factories\TaskAttachmentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Number;

final class TaskAttachment extends Model
{
    /** @use HasFactory<TaskAttachmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'task_id',
        'uploader_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    protected $appends = [
        'size_for_humans',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id')->withTrashed();
    }

    /**
     * @return Attribute<string, never>
     */
    protected function sizeForHumans(): Attribute
    {
        return Attribute::get(fn (): string => Number::fileSize($this->size_bytes, precision: 1));
    }
}
```

- [ ] **Step 5: Viết factory**

Tạo `database/factories/TaskAttachmentFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskAttachment>
 */
final class TaskAttachmentFactory extends Factory
{
    protected $model = TaskAttachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->slug(3).'.pdf';

        return [
            'task_id' => Task::factory(),
            'uploader_id' => User::factory(),
            'disk' => 'local',
            'path' => 'task-attachments/1/'.$this->faker->uuid().'.pdf',
            'original_name' => $name,
            'mime_type' => 'application/pdf',
            'size_bytes' => $this->faker->numberBetween(1024, 5 * 1024 * 1024),
        ];
    }
}
```

- [ ] **Step 6: Thêm quan hệ vào Task**

Trong `app/Models/Task.php`, ngay dưới method `comments()`:

```php
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class)->latest('id');
    }
```

- [ ] **Step 7: Chạy test để xác nhận pass**

Run: `php artisan test tests/Feature/Task/TaskAttachmentModelTest.php`
Expected: PASS, 6 test (3 case của dataset `size_for_humans` tính riêng).

Nếu `size_for_humans` sai định dạng, in ra giá trị thực tế và sửa **test** cho khớp đầu ra của `Number::fileSize()` trên phiên bản Laravel đang dùng — định dạng số là chi tiết của framework, không phải yêu cầu nghiệp vụ. `Number::fileSize()` chỉ nhận `$bytes`, `$precision`, `$maxPrecision`; không có tham số locale.

- [ ] **Step 8: Commit**

```bash
./vendor/bin/pint app/Models/TaskAttachment.php app/Models/Task.php database/factories/TaskAttachmentFactory.php
git add database/migrations/2026_07_29_090000_create_task_attachments_table.php app/Models/TaskAttachment.php database/factories/TaskAttachmentFactory.php app/Models/Task.php tests/Feature/Task/TaskAttachmentModelTest.php
git commit -m "feat(task): add task attachment model and table"
```

---

### Task 2: Policy cho đính kèm

**Files:**
- Modify: `app/Policies/TaskPolicy.php` (thêm `attach`, `downloadAttachment` sau method `comment`)
- Create: `app/Policies/TaskAttachmentPolicy.php`
- Test: `tests/Feature/Task/TaskAttachmentPolicyTest.php`

**Interfaces:**
- Consumes: `TaskAttachment` (Task 1), `App\Enums\PermissionName`.
- Produces: `TaskPolicy::attach(User, Task): bool`, `TaskPolicy::downloadAttachment(User, Task): bool`, `TaskAttachmentPolicy::delete(User, TaskAttachment): bool`. Policy được Laravel tự khám phá theo quy ước tên (`App\Models\TaskAttachment` → `App\Policies\TaskAttachmentPolicy`), không cần đăng ký thủ công.

- [ ] **Step 1: Viết test thất bại**

Tạo `tests/Feature/Task/TaskAttachmentPolicyTest.php`:

```php
<?php

use App\Enums\PermissionName;
use App\Models\Task;
use App\Models\TaskAttachment;

test('attaching requires both view and comment permissions', function (array $permissions, bool $expected) {
    $user = userWithPermissions($permissions);
    $task = Task::factory()->create();

    expect($user->can('attach', $task))->toBe($expected);
})->with([
    'view and comment' => [[PermissionName::TaskView->value, PermissionName::TaskComment->value], true],
    'view only' => [[PermissionName::TaskView->value], false],
    'comment only' => [[PermissionName::TaskComment->value], false],
    'none' => [[], false],
]);

test('downloading an attachment requires the task view permission', function (array $permissions, bool $expected) {
    $user = userWithPermissions($permissions);
    $task = Task::factory()->create();

    expect($user->can('downloadAttachment', $task))->toBe($expected);
})->with([
    'view' => [[PermissionName::TaskView->value], true],
    'none' => [[], false],
]);

test('the uploader can delete their own attachment', function () {
    $uploader = userWithPermissions([PermissionName::TaskView->value]);
    $attachment = TaskAttachment::factory()->for($uploader, 'uploader')->create();

    expect($uploader->can('delete', $attachment))->toBeTrue();
});

test('a user with the task update permission can delete any attachment', function () {
    $manager = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskUpdate->value,
    ]);
    $attachment = TaskAttachment::factory()->create();

    expect($manager->can('delete', $attachment))->toBeTrue();
});

test('another user without the task update permission cannot delete an attachment', function () {
    $other = userWithPermissions([PermissionName::TaskView->value]);
    $attachment = TaskAttachment::factory()->create();

    expect($other->can('delete', $attachment))->toBeFalse();
});
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run: `php artisan test tests/Feature/Task/TaskAttachmentPolicyTest.php`
Expected: FAIL — `can('attach', ...)` trả `false` vì method chưa tồn tại.

- [ ] **Step 3: Thêm method vào TaskPolicy**

Trong `app/Policies/TaskPolicy.php`, ngay sau method `comment()`:

```php
    public function attach(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskView->value)
            && $user->can(PermissionName::TaskComment->value);
    }

    public function downloadAttachment(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskView->value);
    }
```

- [ ] **Step 4: Viết TaskAttachmentPolicy**

Tạo `app/Policies/TaskAttachmentPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\TaskAttachment;
use App\Models\User;

final class TaskAttachmentPolicy
{
    public function delete(User $user, TaskAttachment $attachment): bool
    {
        return $attachment->uploader_id === $user->id
            || $user->can(PermissionName::TaskUpdate->value);
    }
}
```

- [ ] **Step 5: Chạy test để xác nhận pass**

Run: `php artisan test tests/Feature/Task/TaskAttachmentPolicyTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint app/Policies/TaskPolicy.php app/Policies/TaskAttachmentPolicy.php
git add app/Policies/TaskPolicy.php app/Policies/TaskAttachmentPolicy.php tests/Feature/Task/TaskAttachmentPolicyTest.php
git commit -m "feat(task): authorize task attachment actions"
```

---

### Task 3: Tải tệp lên

**Files:**
- Create: `app/Http/Requests/StoreTaskAttachmentRequest.php`
- Create: `app/Actions/Task/StoreTaskAttachmentAction.php`
- Create: `app/Http/Controllers/TaskAttachmentController.php`
- Modify: `app/Enums/AuditAction.php` (thêm 2 case)
- Modify: `routes/web.php` (thêm route `tasks.attachments.store`, đặt trước `Route::resource('tasks', ...)`)
- Test: `tests/Feature/Task/TaskAttachmentControllerTest.php`

**Interfaces:**
- Consumes: `TaskAttachment` (Task 1), `TaskPolicy::attach` (Task 2), `App\Services\AuditLogger::record(User $actor, AuditAction $action, Model $subject, array $beforeValues = [], array $afterValues = [], array $metadata = [])`.
- Produces: `StoreTaskAttachmentAction::execute(User $actor, Task $task, array $files): void` (`$files` là `list<UploadedFile>`); route name `tasks.attachments.store`; `AuditAction::TaskAttachmentUploaded`, `AuditAction::TaskAttachmentDeleted`.

- [ ] **Step 1: Viết test thất bại**

Tạo `tests/Feature/Task/TaskAttachmentControllerTest.php`:

```php
<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function uploaderUser(): App\Models\User
{
    return userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskComment->value,
    ]);
}

test('a user who can comment uploads files to a task', function () {
    Storage::fake('local');

    $uploader = uploaderUser();
    $task = Task::factory()->create();

    $this->actingAs($uploader)
        ->post(route('tasks.attachments.store', $task), [
            'files' => [
                UploadedFile::fake()->create('bao-cao.pdf', 120, 'application/pdf'),
                UploadedFile::fake()->create('so-lieu.xlsx', 80, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ],
        ])
        ->assertRedirect(route('tasks.show', $task))
        ->assertSessionHas('success');

    $attachments = TaskAttachment::query()->get();

    expect($attachments)->toHaveCount(2)
        ->and($attachments->pluck('original_name')->all())->toContain('bao-cao.pdf', 'so-lieu.xlsx')
        ->and($attachments->pluck('task_id')->unique()->all())->toBe([$task->id])
        ->and($attachments->pluck('uploader_id')->unique()->all())->toBe([$uploader->id])
        ->and($attachments->pluck('disk')->unique()->all())->toBe(['local']);

    foreach ($attachments as $attachment) {
        Storage::disk('local')->assertExists($attachment->path);
        expect($attachment->path)->toStartWith("task-attachments/{$task->id}/")
            ->and($attachment->path)->not->toContain($attachment->original_name);
    }
});

test('uploading records a single audit entry for the request', function () {
    Storage::fake('local');

    $uploader = uploaderUser();
    $task = Task::factory()->create();

    $this->actingAs($uploader)
        ->post(route('tasks.attachments.store', $task), [
            'files' => [
                UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 20, 'application/pdf'),
            ],
        ]);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $uploader->id,
        'action' => AuditAction::TaskAttachmentUploaded->value,
        'subject_type' => $task->getMorphClass(),
        'subject_id' => $task->id,
    ]);

    expect(App\Models\AuditLog::query()->where('action', AuditAction::TaskAttachmentUploaded->value)->count())->toBe(1);
});

test('uploading requires both view and comment permissions', function () {
    Storage::fake('local');

    $user = userWithPermissions([PermissionName::TaskView->value]);
    $task = Task::factory()->create();

    $this->actingAs($user)
        ->post(route('tasks.attachments.store', $task), [
            'files' => [UploadedFile::fake()->create('bao-cao.pdf', 10, 'application/pdf')],
        ])
        ->assertForbidden();

    expect(TaskAttachment::query()->count())->toBe(0);
});

test('an executable file type is rejected', function () {
    Storage::fake('local');

    $task = Task::factory()->create();

    $this->actingAs(uploaderUser())
        ->post(route('tasks.attachments.store', $task), [
            'files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
        ])
        ->assertSessionHasErrors('files.0');

    expect(TaskAttachment::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('a file larger than ten megabytes is rejected', function () {
    Storage::fake('local');

    $task = Task::factory()->create();

    $this->actingAs(uploaderUser())
        ->post(route('tasks.attachments.store', $task), [
            'files' => [UploadedFile::fake()->create('lon.pdf', 10241, 'application/pdf')],
        ])
        ->assertSessionHasErrors('files.0');

    expect(TaskAttachment::query()->count())->toBe(0);
});

test('more than five files in one request are rejected', function () {
    Storage::fake('local');

    $task = Task::factory()->create();
    $files = [];

    for ($index = 0; $index < 6; $index++) {
        $files[] = UploadedFile::fake()->create("tep-{$index}.pdf", 10, 'application/pdf');
    }

    $this->actingAs(uploaderUser())
        ->post(route('tasks.attachments.store', $task), ['files' => $files])
        ->assertSessionHasErrors('files');

    expect(TaskAttachment::query()->count())->toBe(0);
});

test('at least one file is required', function () {
    Storage::fake('local');

    $task = Task::factory()->create();

    $this->actingAs(uploaderUser())
        ->post(route('tasks.attachments.store', $task), [])
        ->assertSessionHasErrors('files');
});
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run: `php artisan test tests/Feature/Task/TaskAttachmentControllerTest.php`
Expected: FAIL với `Route [tasks.attachments.store] not defined`.

- [ ] **Step 3: Thêm case vào AuditAction**

Trong `app/Enums/AuditAction.php`, sau `case TaskDeleted`:

```php
    case TaskAttachmentUploaded = 'task.attachment_uploaded';
    case TaskAttachmentDeleted = 'task.attachment_deleted';
```

- [ ] **Step 4: Viết FormRequest**

Tạo `app/Http/Requests/StoreTaskAttachmentRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class StoreTaskAttachmentRequest extends FormRequest
{
    /**
     * MIME được phép đính kèm. Kiểm tra bằng MIME thật của tệp, không theo phần mở rộng.
     *
     * @var list<string>
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/csv',
        'text/plain',
        'application/zip',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('attach', $this->route('task'));
    }

    protected function prepareForValidation(): void
    {
        // Khi tổng dung lượng vượt post_max_size của PHP, body bị bỏ trắng trước khi
        // tới Laravel. Không xử lý thì người dùng nhận lỗi "files là bắt buộc" gây hiểu nhầm.
        $contentLength = (int) $this->server('CONTENT_LENGTH', 0);

        if ($contentLength > 0 && $this->all() === [] && $this->allFiles() === []) {
            throw ValidationException::withMessages([
                'files' => 'Tổng dung lượng vượt quá giới hạn máy chủ cho phép. Vui lòng tải ít tệp hơn trong một lần.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => [
                'required',
                'file',
                'max:10240',
                'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Vui lòng chọn ít nhất một tệp.',
            'files.max' => 'Mỗi lần chỉ tải lên tối đa 5 tệp.',
            'files.*.max' => 'Mỗi tệp không được vượt quá 10MB.',
            'files.*.mimetypes' => 'Định dạng tệp không được phép đính kèm.',
        ];
    }
}
```

- [ ] **Step 5: Viết Action**

Tạo `app/Actions/Task/StoreTaskAttachmentAction.php`:

```php
<?php

namespace App\Actions\Task;

use App\Enums\AuditAction;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class StoreTaskAttachmentAction
{
    private const DISK = 'local';

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function execute(User $actor, Task $task, array $files): void
    {
        // Ghi disk trước, ngoài transaction: thao tác I/O chậm không được nằm trong transaction.
        $storedPaths = [];
        $rows = [];

        foreach ($files as $file) {
            $path = $file->store("task-attachments/{$task->id}", self::DISK);

            if (! is_string($path)) {
                $this->discard($storedPaths);

                throw ValidationException::withMessages([
                    'files' => 'Không thể lưu tệp đính kèm. Vui lòng thử lại.',
                ]);
            }

            $storedPaths[] = $path;
            $rows[] = [
                'uploader_id' => $actor->id,
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
            ];
        }

        try {
            DB::transaction(function () use ($actor, $task, $rows): void {
                foreach ($rows as $row) {
                    $task->attachments()->create($row);
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
            });
        } catch (Throwable $exception) {
            $this->discard($storedPaths);

            throw $exception;
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function discard(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
```

- [ ] **Step 6: Viết Controller**

Tạo `app/Http/Controllers/TaskAttachmentController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Actions\Task\StoreTaskAttachmentAction;
use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

final class TaskAttachmentController extends Controller
{
    public function store(
        StoreTaskAttachmentRequest $request,
        Task $task,
        StoreTaskAttachmentAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $task, array_values($request->file('files')));

        return Redirect::route('tasks.show', $task)->with('success', 'Đã tải tệp đính kèm lên.');
    }
}
```

- [ ] **Step 7: Thêm route**

Trong `routes/web.php`, thêm `use App\Http\Controllers\TaskAttachmentController;` vào khối `use`, rồi thêm dòng sau ngay dưới route `tasks.comments.store` (tức là **trước** `Route::resource('tasks', TaskController::class)`):

```php
    Route::post('tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])
        ->name('tasks.attachments.store');
```

- [ ] **Step 8: Chạy test để xác nhận pass**

Run: `php artisan test tests/Feature/Task/TaskAttachmentControllerTest.php`
Expected: PASS toàn bộ 7 test.

- [ ] **Step 9: Commit**

```bash
./vendor/bin/pint app/ routes/web.php
git add app/Http/Requests/StoreTaskAttachmentRequest.php app/Actions/Task/StoreTaskAttachmentAction.php app/Http/Controllers/TaskAttachmentController.php app/Enums/AuditAction.php routes/web.php tests/Feature/Task/TaskAttachmentControllerTest.php
git commit -m "feat(task): upload attachments to a task"
```

---

### Task 4: Tải tệp xuống

**Files:**
- Modify: `app/Http/Controllers/TaskAttachmentController.php` (thêm method `download`)
- Modify: `routes/web.php` (thêm route `tasks.attachments.download` với scoped binding)
- Test: `tests/Feature/Task/TaskAttachmentControllerTest.php` (thêm test vào cuối file)

**Interfaces:**
- Consumes: `TaskPolicy::downloadAttachment` (Task 2), `TaskAttachment` (Task 1).
- Produces: route name `tasks.attachments.download` nhận `{task}` và `{attachment}`.

- [ ] **Step 1: Viết test thất bại**

Thêm vào cuối `tests/Feature/Task/TaskAttachmentControllerTest.php`:

```php
test('a user who can view the task downloads an attachment under its original name', function () {
    Storage::fake('local');

    $task = Task::factory()->create();
    $path = "task-attachments/{$task->id}/luu-tru.pdf";
    Storage::disk('local')->put($path, 'noi dung tep');

    $attachment = TaskAttachment::factory()->for($task)->create([
        'path' => $path,
        'original_name' => 'Báo cáo quý.pdf',
    ]);

    $viewer = userWithPermissions([PermissionName::TaskView->value]);

    $response = $this->actingAs($viewer)
        ->get(route('tasks.attachments.download', [$task, $attachment]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment');
    expect($response->streamedContent())->toBe('noi dung tep');
});

test('a user without the task view permission cannot download an attachment', function () {
    Storage::fake('local');

    $task = Task::factory()->create();
    $path = "task-attachments/{$task->id}/luu-tru.pdf";
    Storage::disk('local')->put($path, 'noi dung tep');

    $attachment = TaskAttachment::factory()->for($task)->create(['path' => $path]);

    $this->actingAs(userWithPermissions([]))
        ->get(route('tasks.attachments.download', [$task, $attachment]))
        ->assertForbidden();
});

test('an attachment cannot be reached through a different task', function () {
    Storage::fake('local');

    $task = Task::factory()->create();
    $otherTask = Task::factory()->create();
    $attachment = TaskAttachment::factory()->for($otherTask)->create();

    $this->actingAs(userWithPermissions([PermissionName::TaskView->value]))
        ->get(route('tasks.attachments.download', [$task, $attachment]))
        ->assertNotFound();
});
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run: `php artisan test tests/Feature/Task/TaskAttachmentControllerTest.php --filter="download"`
Expected: FAIL với `Route [tasks.attachments.download] not defined`.

- [ ] **Step 3: Thêm method download vào controller**

Trong `app/Http/Controllers/TaskAttachmentController.php`, thêm import `use App\Models\TaskAttachment;` và `use Symfony\Component\HttpFoundation\StreamedResponse;`, rồi thêm method:

```php
    public function download(Task $task, TaskAttachment $attachment): StreamedResponse
    {
        $this->authorize('downloadAttachment', $task);

        return Storage::disk($attachment->disk)
            ->download($attachment->path, $attachment->original_name);
    }
```

Thêm `use Illuminate\Support\Facades\Storage;` vào khối import.

- [ ] **Step 4: Thêm route**

Trong `routes/web.php`, ngay dưới route `tasks.attachments.store`:

```php
    Route::get('tasks/{task}/attachments/{attachment}/download', [TaskAttachmentController::class, 'download'])
        ->scopeBindings()
        ->name('tasks.attachments.download');
```

`scopeBindings()` buộc `{attachment}` phải thuộc `{task}` trong URL; nếu không thuộc, Laravel trả 404.

- [ ] **Step 5: Chạy test để xác nhận pass**

Run: `php artisan test tests/Feature/Task/TaskAttachmentControllerTest.php`
Expected: PASS toàn bộ 10 test.

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint app/ routes/web.php
git add app/Http/Controllers/TaskAttachmentController.php routes/web.php tests/Feature/Task/TaskAttachmentControllerTest.php
git commit -m "feat(task): download task attachments through an authorized route"
```

---

### Task 5: Xoá tệp đính kèm

**Files:**
- Create: `app/Actions/Task/DeleteTaskAttachmentAction.php`
- Modify: `app/Http/Controllers/TaskAttachmentController.php` (thêm method `destroy`)
- Modify: `routes/web.php` (thêm route `tasks.attachments.destroy`)
- Test: `tests/Feature/Task/TaskAttachmentControllerTest.php` (thêm test vào cuối file)

**Interfaces:**
- Consumes: `TaskAttachmentPolicy::delete` (Task 2), `AuditLogger`, `AuditAction::TaskAttachmentDeleted` (Task 3).
- Produces: `DeleteTaskAttachmentAction::execute(User $actor, TaskAttachment $attachment): void`; route name `tasks.attachments.destroy`.

- [ ] **Step 1: Viết test thất bại**

Thêm vào cuối `tests/Feature/Task/TaskAttachmentControllerTest.php`:

```php
test('the uploader deletes their attachment and the file stays on disk', function () {
    Storage::fake('local');

    $uploader = uploaderUser();
    $task = Task::factory()->create();
    $path = "task-attachments/{$task->id}/luu-tru.pdf";
    Storage::disk('local')->put($path, 'noi dung tep');

    $attachment = TaskAttachment::factory()->for($task)->for($uploader, 'uploader')->create([
        'path' => $path,
    ]);

    $this->actingAs($uploader)
        ->delete(route('tasks.attachments.destroy', [$task, $attachment]))
        ->assertRedirect(route('tasks.show', $task))
        ->assertSessionHas('success');

    expect(TaskAttachment::count())->toBe(0)
        ->and(TaskAttachment::withTrashed()->count())->toBe(1);

    Storage::disk('local')->assertExists($path);

    $this->assertDatabaseHas('audit_logs', [
        'actor_id' => $uploader->id,
        'action' => AuditAction::TaskAttachmentDeleted->value,
        'subject_type' => $attachment->getMorphClass(),
        'subject_id' => $attachment->id,
    ]);
});

test('a user with the task update permission deletes an attachment uploaded by someone else', function () {
    Storage::fake('local');

    $manager = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskUpdate->value,
    ]);
    $task = Task::factory()->create();
    $attachment = TaskAttachment::factory()->for($task)->create();

    $this->actingAs($manager)
        ->delete(route('tasks.attachments.destroy', [$task, $attachment]))
        ->assertRedirect(route('tasks.show', $task));

    expect(TaskAttachment::count())->toBe(0);
});

test('another user cannot delete an attachment they did not upload', function () {
    Storage::fake('local');

    $task = Task::factory()->create();
    $attachment = TaskAttachment::factory()->for($task)->create();

    $this->actingAs(userWithPermissions([PermissionName::TaskView->value]))
        ->delete(route('tasks.attachments.destroy', [$task, $attachment]))
        ->assertForbidden();

    expect(TaskAttachment::count())->toBe(1);
});
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run: `php artisan test tests/Feature/Task/TaskAttachmentControllerTest.php --filter="delete"`
Expected: FAIL với `Route [tasks.attachments.destroy] not defined`.

- [ ] **Step 3: Viết Action**

Tạo `app/Actions/Task/DeleteTaskAttachmentAction.php`:

```php
<?php

namespace App\Actions\Task;

use App\Enums\AuditAction;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final readonly class DeleteTaskAttachmentAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function execute(User $actor, TaskAttachment $attachment): void
    {
        // Giữ file trên disk: bản ghi xoá mềm nên vẫn khôi phục được khi xoá nhầm.
        DB::transaction(function () use ($actor, $attachment): void {
            $this->auditLogger->record(
                actor: $actor,
                action: AuditAction::TaskAttachmentDeleted,
                subject: $attachment,
                metadata: [
                    'task_id' => $attachment->task_id,
                    'original_name' => $attachment->original_name,
                    'size_bytes' => $attachment->size_bytes,
                ],
            );

            $attachment->delete();
        });
    }
}
```

- [ ] **Step 4: Thêm method destroy vào controller**

Trong `app/Http/Controllers/TaskAttachmentController.php`, thêm import `use App\Actions\Task\DeleteTaskAttachmentAction;` và `use Illuminate\Http\Request;`, rồi thêm method:

```php
    public function destroy(
        Request $request,
        Task $task,
        TaskAttachment $attachment,
        DeleteTaskAttachmentAction $action,
    ): RedirectResponse {
        $this->authorize('delete', $attachment);

        $action->execute($request->user(), $attachment);

        return Redirect::route('tasks.show', $task)->with('success', 'Đã xoá tệp đính kèm.');
    }
```

- [ ] **Step 5: Thêm route**

Trong `routes/web.php`, ngay dưới route `tasks.attachments.download`:

```php
    Route::delete('tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])
        ->scopeBindings()
        ->name('tasks.attachments.destroy');
```

- [ ] **Step 6: Chạy test để xác nhận pass**

Run: `php artisan test tests/Feature/Task/TaskAttachmentControllerTest.php`
Expected: PASS toàn bộ 13 test.

- [ ] **Step 7: Commit**

```bash
./vendor/bin/pint app/ routes/web.php
git add app/Actions/Task/DeleteTaskAttachmentAction.php app/Http/Controllers/TaskAttachmentController.php routes/web.php tests/Feature/Task/TaskAttachmentControllerTest.php
git commit -m "feat(task): soft delete task attachments with an audit trail"
```

---

### Task 6: Đưa dữ liệu đính kèm ra trang chi tiết

**Files:**
- Modify: `app/Http/Controllers/TaskController.php:87-129` (method `show`)
- Test: `tests/Feature/Task/TaskAttachmentControllerTest.php` (thêm test vào cuối file)

**Interfaces:**
- Consumes: `Task::attachments()` (Task 1), `TaskAttachmentPolicy::delete` (Task 2).
- Produces: prop Inertia `attachments` — mảng phần tử `{ id, original_name, mime_type, size_bytes, size_for_humans, created_at, uploader: { id, name, avatar_url } | null, can_delete: bool }`; prop `actions.attach: bool`.

- [ ] **Step 1: Viết test thất bại**

Thêm vào cuối `tests/Feature/Task/TaskAttachmentControllerTest.php`:

```php
test('task details expose attachments with per user delete permission', function () {
    Storage::fake('local');

    $uploader = uploaderUser();
    $task = Task::factory()->create();

    $own = TaskAttachment::factory()->for($task)->for($uploader, 'uploader')->create([
        'original_name' => 'cua-toi.pdf',
    ]);
    $foreign = TaskAttachment::factory()->for($task)->create([
        'original_name' => 'cua-nguoi-khac.pdf',
    ]);

    $this->actingAs($uploader)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('attachments', 2)
            ->where('attachments.0.id', $foreign->id)
            ->where('attachments.0.can_delete', false)
            ->where('attachments.1.id', $own->id)
            ->where('attachments.1.can_delete', true)
            ->where('attachments.1.original_name', 'cua-toi.pdf')
            ->has('attachments.1.size_for_humans')
            ->where('actions.attach', true));
});

test('a viewer without the comment permission cannot attach from the task page', function () {
    $task = Task::factory()->create();

    $this->actingAs(userWithPermissions([PermissionName::TaskView->value]))
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('actions.attach', false));
});

test('deleted attachments disappear from the task page', function () {
    $task = Task::factory()->create();
    $attachment = TaskAttachment::factory()->for($task)->create();
    $attachment->delete();

    $this->actingAs(userWithPermissions([PermissionName::TaskView->value]))
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('attachments', 0));
});
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run: `php artisan test tests/Feature/Task/TaskAttachmentControllerTest.php --filter="task details expose attachments"`
Expected: FAIL — prop `attachments` không tồn tại.

- [ ] **Step 3: Bổ sung dữ liệu vào TaskController::show**

Trong `app/Http/Controllers/TaskController.php`, trong method `show()`, ngay sau khối `$comments = ...->withQueryString();`:

```php
        $attachments = $task->attachments()
            ->with('uploader:id,name,avatar_path')
            ->get()
            ->map(fn (TaskAttachment $attachment): array => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'size_for_humans' => $attachment->size_for_humans,
                'created_at' => $attachment->created_at,
                'uploader' => $attachment->uploader === null ? null : [
                    'id' => $attachment->uploader->id,
                    'name' => $attachment->uploader->name,
                    'avatar_url' => $attachment->uploader->avatar_url,
                ],
                'can_delete' => request()->user()->can('delete', $attachment),
            ])
            ->all();
```

Thêm `use App\Models\TaskAttachment;` vào khối import.

Trong mảng trả về của `Inertia::render('Tasks/Show', [...])`, thêm `'attachments' => $attachments,` ngay sau `'comments' => $comments,`; và trong mảng `'actions'`, thêm sau dòng `'comment' => ...`:

```php
                'attach' => request()->user()->can('attach', $task),
```

- [ ] **Step 4: Chạy test để xác nhận pass**

Run: `php artisan test tests/Feature/Task/TaskAttachmentControllerTest.php`
Expected: PASS toàn bộ 16 test.

- [ ] **Step 5: Chạy toàn bộ test backend**

Run: `php artisan test`
Expected: PASS toàn bộ. Nếu `TaskControllerTest` hỏng vì prop mới, sửa assertion ở đó cho khớp — không gỡ prop.

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint app/Http/Controllers/TaskController.php
git add app/Http/Controllers/TaskController.php tests/Feature/Task/TaskAttachmentControllerTest.php
git commit -m "feat(task): expose attachments on the task detail page"
```

---

### Task 7: Giao diện đính kèm

**Files:**
- Create: `resources/js/Components/TaskAttachmentList.vue`
- Modify: `resources/js/types/index.d.ts` (thêm interface `TaskAttachment` sau `TaskComment`)
- Modify: `resources/js/Pages/Tasks/Show.vue` (import component, khai báo prop, đặt mục đính kèm dưới mục trao đổi)

**Interfaces:**
- Consumes: prop `attachments` và `actions.attach` (Task 6); route `tasks.attachments.store`, `tasks.attachments.download`, `tasks.attachments.destroy` (Task 3–5).
- Produces: component `TaskAttachmentList` nhận props `{ taskId: number; attachments: TaskAttachment[]; canAttach: boolean }`.

- [ ] **Step 1: Thêm kiểu TypeScript**

Trong `resources/js/types/index.d.ts`, ngay sau interface `TaskComment`:

```ts
export interface TaskAttachment {
    id: number;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    size_for_humans: string;
    created_at: string;
    uploader?: Pick<User, 'id' | 'name' | 'avatar_url'> | null;
    can_delete: boolean;
}
```

- [ ] **Step 2: Viết component**

Tạo `resources/js/Components/TaskAttachmentList.vue`:

```vue
<script setup lang="ts">
import { ref } from 'vue';
import AppConfirmDialog from '@/Components/AppConfirmDialog.vue';
import AppIcon from '@/Components/AppIcon.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { router, useForm } from '@inertiajs/vue3';
import type { TaskAttachment } from '@/types';

const props = defineProps<{
    taskId: number;
    attachments: TaskAttachment[];
    canAttach: boolean;
}>();

const MAX_FILES = 5;

const form = useForm<{ files: File[] }>({ files: [] });
const fileInput = ref<HTMLInputElement | null>(null);
const isDraggingOver = ref(false);
const attachmentPendingDeletion = ref<TaskAttachment | null>(null);
const isDeleting = ref(false);

const addFiles = (incoming: FileList | null) => {
    if (!incoming) {
        return;
    }

    form.files = [...form.files, ...Array.from(incoming)].slice(0, MAX_FILES);
    form.clearErrors();
};

const onFileInputChange = (event: Event) => {
    addFiles((event.target as HTMLInputElement).files);
};

const onDrop = (event: DragEvent) => {
    isDraggingOver.value = false;
    addFiles(event.dataTransfer?.files ?? null);
};

const removeSelected = (index: number) => {
    form.files = form.files.filter((_, position) => position !== index);
};

const resetInput = () => {
    form.reset();

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = () => {
    form.post(route('tasks.attachments.store', props.taskId), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: resetInput,
    });
};

const confirmDeletion = () => {
    const attachment = attachmentPendingDeletion.value;

    if (!attachment) {
        return;
    }

    isDeleting.value = true;

    router.delete(route('tasks.attachments.destroy', [props.taskId, attachment.id]), {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            attachmentPendingDeletion.value = null;
        },
    });
};

const formatDateTime = (value: string) =>
    new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' });
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white">
        <header class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-bold text-slate-800">Tệp đính kèm</h2>
            <p class="mt-1 text-xs text-slate-500">
                {{ attachments.length }} tệp trong công việc này.
            </p>
        </header>

        <form v-if="canAttach" class="border-b border-slate-100 px-5 py-4" @submit.prevent="submit">
            <div
                class="rounded-xl border-2 border-dashed px-4 py-6 text-center transition"
                :class="isDraggingOver ? 'border-brand-400 bg-brand-50' : 'border-slate-200'"
                @dragover.prevent="isDraggingOver = true"
                @dragleave.prevent="isDraggingOver = false"
                @drop.prevent="onDrop"
            >
                <p class="text-sm text-slate-600">Kéo thả tệp vào đây hoặc</p>
                <button
                    type="button"
                    class="mt-1 text-sm font-semibold text-brand-700 underline"
                    @click="fileInput?.click()"
                >
                    chọn tệp từ máy
                </button>
                <p class="mt-2 text-xs text-slate-400">
                    Tối đa {{ MAX_FILES }} tệp mỗi lần, mỗi tệp không quá 10MB. Hỗ trợ PDF, Word,
                    Excel, PowerPoint, CSV, TXT, ZIP và ảnh.
                </p>
                <input
                    ref="fileInput"
                    type="file"
                    multiple
                    class="hidden"
                    @change="onFileInputChange"
                />
            </div>

            <ul v-if="form.files.length" class="mt-3 space-y-2">
                <li
                    v-for="(file, index) in form.files"
                    :key="`${file.name}-${index}`"
                    class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"
                >
                    <span class="truncate text-sm text-slate-700">{{ file.name }}</span>
                    <button
                        type="button"
                        class="text-xs font-semibold text-slate-500 hover:text-red-600"
                        @click="removeSelected(index)"
                    >
                        Bỏ
                    </button>
                </li>
            </ul>

            <InputError class="mt-2" :message="form.errors.files" />
            <InputError
                v-for="index in MAX_FILES"
                :key="`error-${index}`"
                class="mt-1"
                :message="form.errors[`files.${index - 1}` as keyof typeof form.errors] as string"
            />

            <div class="mt-3 flex items-center gap-3">
                <PrimaryButton :disabled="form.processing || !form.files.length">
                    {{ form.processing ? 'Đang tải lên...' : 'Tải lên' }}
                </PrimaryButton>
                <span v-if="form.progress" class="text-xs text-slate-500">
                    {{ form.progress.percentage }}%
                </span>
            </div>
        </form>

        <ul v-if="attachments.length" class="divide-y divide-slate-100">
            <li
                v-for="attachment in attachments"
                :key="attachment.id"
                class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-800">
                        {{ attachment.original_name }}
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ attachment.size_for_humans }} ·
                        {{ attachment.uploader?.name ?? 'Tài khoản đã xóa' }} ·
                        <time :datetime="attachment.created_at">
                            {{ formatDateTime(attachment.created_at) }}
                        </time>
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <a
                        :href="route('tasks.attachments.download', [taskId, attachment.id])"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:underline"
                    >
                        <AppIcon name="arrow-left" class="size-4 rotate-[-90deg]" />
                        Tải xuống
                    </a>
                    <button
                        v-if="attachment.can_delete"
                        type="button"
                        class="text-xs font-semibold text-slate-500 hover:text-red-600"
                        @click="attachmentPendingDeletion = attachment"
                    >
                        Xoá
                    </button>
                </div>
            </li>
        </ul>

        <p v-else class="px-5 py-6 text-center text-sm text-slate-500">
            Chưa có tệp đính kèm.
            <span v-if="canAttach">Tải tệp đầu tiên lên bằng khu vực phía trên.</span>
        </p>

        <AppConfirmDialog
            :show="attachmentPendingDeletion !== null"
            title="Xoá tệp đính kèm?"
            :description="`Tệp “${attachmentPendingDeletion?.original_name ?? ''}” sẽ không còn hiển thị trong công việc. Thao tác này được ghi vào nhật ký.`"
            confirm-label="Xoá tệp"
            :processing="isDeleting"
            @cancel="attachmentPendingDeletion = null"
            @confirm="confirmDeletion"
        />
    </section>
</template>
```

- [ ] **Step 3: Nhúng vào trang chi tiết**

Trong `resources/js/Pages/Tasks/Show.vue`:

1. Thêm import: `import TaskAttachmentList from '@/Components/TaskAttachmentList.vue';`
2. Sửa dòng import type thành: `import type { PageProps, Task, TaskAttachment, TaskComment } from '@/types';`
3. Trong `defineProps`, thêm `attachments: TaskAttachment[];` sau `comments: PaginatedComments;` và thêm `attach: boolean;` vào object `actions`.
4. Trong template, ngay sau khối `<section>` chứa phần trao đổi/bình luận, thêm:

```vue
                <TaskAttachmentList
                    class="mt-6"
                    :task-id="task.id"
                    :attachments="attachments"
                    :can-attach="actions.attach"
                />
```

- [ ] **Step 4: Kiểm tra build và lint**

Run: `npm run lint`
Expected: không lỗi.

Run: `npm run build`
Expected: build thành công, không lỗi TypeScript.

- [ ] **Step 5: Kiểm tra thủ công trên trình duyệt**

Chạy `php artisan serve` và `npm run dev`, đăng nhập bằng tài khoản admin của seeder, mở một công việc rồi kiểm tra:

1. Tải lên 2 tệp cùng lúc → xuất hiện trong danh sách, thông báo thành công.
2. Bấm "Tải xuống" → tệp tải về đúng tên gốc.
3. Bấm "Xoá" → hộp thoại xác nhận hiện ra, xác nhận thì tệp biến mất khỏi danh sách.
4. Thử tệp `.exe` → hiện lỗi định dạng ngay dưới vùng upload, không tải lên.
5. Thu nhỏ cửa sổ xuống chiều rộng mobile → danh sách xếp dọc, không tràn ngang.
6. Đăng nhập bằng tài khoản chỉ có `task.view` → không thấy khu vực upload và nút xoá.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/TaskAttachmentList.vue resources/js/Pages/Tasks/Show.vue resources/js/types/index.d.ts
git commit -m "feat(task): add attachment panel to the task detail page"
```

---

### Task 8: Cập nhật tài liệu

**Files:**
- Modify: `docs/srs/SRS.md` (mục 3.6 FR-TASK-10, mục 10.4 bảng phụ trợ, mục 11 trạng thái Sprint 2, mục 14 lịch sử phiên bản)
- Modify: `context/sprint-plan.md` (phần "Trạng thái hiện tại")
- Regenerate: `docs/srs/DORMIDA-WORK-SRS.pdf`

**Interfaces:**
- Consumes: toàn bộ kết quả Task 1–7.
- Produces: tài liệu khớp với code, đúng yêu cầu "Tài liệu liên quan đã cập nhật" trong `README.md` §6.

- [ ] **Step 1: Cập nhật SRS**

Trong `docs/srs/SRS.md`:

1. Mục 3.6, dòng FR-TASK-10: đổi cột trạng thái từ `Đang triển khai` thành `Đã triển khai`.
2. Mục 10.4, thêm một dòng vào bảng phụ trợ:

```markdown
| `task_attachments` | Tệp đính kèm của công việc: disk, path, tên gốc, MIME, dung lượng, người tải lên — xoá mềm |
```

3. Mục 11, đoạn dưới bảng: đổi thành đã hoàn tất thêm tệp đính kèm, công việc kế tiếp chỉ còn activity timeline hợp nhất.
4. Mục 14, thêm dòng phiên bản mới:

```markdown
| 1.1 | 29/07/2026 | Cập nhật FR-TASK-10 (tệp đính kèm) sang trạng thái đã triển khai |
```

Đồng thời sửa ô "Phiên bản tài liệu" ở bảng đầu file thành `1.1` và ngày phát hành thành `29/07/2026`.

- [ ] **Step 2: Cập nhật sprint-plan**

Trong `context/sprint-plan.md`, phần "Trạng thái hiện tại": bổ sung tệp đính kèm vào danh sách đã hoàn tất của Sprint 2, và sửa dòng "Công việc kế tiếp" thành activity timeline hợp nhất.

- [ ] **Step 3: Render lại PDF**

Run: `node docs/srs/build-pdf.mjs`
Expected: in ra đường dẫn `docs/srs/DORMIDA-WORK-SRS.pdf`, không lỗi.

- [ ] **Step 4: Chạy lại toàn bộ kiểm tra**

```bash
./vendor/bin/pint --test
npm run lint
npm run build
php artisan test
```

Expected: tất cả pass. Đây là đúng bộ lệnh CI chạy trên mỗi PR.

- [ ] **Step 5: Commit**

```bash
git add docs/srs/SRS.md docs/srs/DORMIDA-WORK-SRS.pdf context/sprint-plan.md
git commit -m "docs(task): record attachment support in the SRS and sprint plan"
```

---

## Ghi chú triển khai

**Cấu hình PHP:** để 5 tệp × 10MB đi lọt trong một request, `php.ini` cần `upload_max_filesize >= 10M`, `post_max_size >= 50M` và `max_file_uploads >= 5`. Trên Laragon sửa trong `php.ini` của phiên bản PHP đang chạy rồi khởi động lại. Nếu không đạt, `StoreTaskAttachmentRequest::prepareForValidation()` sẽ trả thông báo tiếng Việt thay vì lỗi khó hiểu — hành vi này kiểm tra thủ công ở Task 7 Step 5, không có test tự động vì không mô phỏng được giới hạn PHP trong Pest.

**Thứ tự route:** mọi route đính kèm phải khai báo trước `Route::resource('tasks', TaskController::class)`, nếu không `tasks/{task}` sẽ nuốt mất `tasks/{task}/attachments`.

**Không nằm trong kế hoạch này** (đã ghi rõ lý do ở mục 11 của spec): đính kèm trong bình luận, bảng attachment polymorphic, preview inline, command dọn file rác, chuyển sang S3/R2, quét virus.
