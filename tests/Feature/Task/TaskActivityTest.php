<?php

use App\Actions\Task\CreateTaskAction;
use App\Actions\Task\CreateTaskCommentAction;
use App\Actions\Task\RecordTaskActivityAction;
use App\Actions\Task\StoreTaskAttachmentAction;
use App\Actions\Task\TransitionTaskStatusAction;
use App\Actions\Task\UpdateTaskAction;
use App\Actions\Task\UpdateTaskProgressAction;
use App\Enums\PermissionName;
use App\Enums\TaskActivityType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\OrganizationUnit;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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

test('creating a task records a created activity', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $task = app(CreateTaskAction::class)->execute($creator, [
        'organization_unit_id' => $unit->id,
        'title' => 'Chuẩn bị báo cáo quý',
        'priority' => TaskPriority::Medium->value,
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
        'status' => TaskStatus::Draft->value,
        'assignee_id' => $assignee->id,
    ]);

    app(TransitionTaskStatusAction::class)->execute(
        $actor,
        $task,
        TaskStatus::Todo,
        TaskStatus::Draft,
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
    $task = Task::factory()->create(['status' => TaskStatus::Draft->value]);

    expect(fn () => app(TransitionTaskStatusAction::class)->execute(
        User::factory()->create(),
        $task,
        TaskStatus::WaitingReview,
    ))->toThrow(ValidationException::class);

    expect(TaskActivity::query()->count())->toBe(0);
});

test('changing the assignee records an activity with both names', function () {
    $actor = User::factory()->create();
    $previous = User::factory()->create(['name' => 'Lan Nguyễn']);
    $next = User::factory()->create(['name' => 'Hùng Trần']);
    $task = Task::factory()->create(['assignee_id' => $previous->id]);

    app(UpdateTaskAction::class)->execute($actor, $task, [
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

    app(UpdateTaskAction::class)->execute(User::factory()->create(), $task, [
        'assignee_id' => null,
    ]);

    $activity = TaskActivity::query()->where('type', 'assigned')->sole();

    expect($activity->payload['to_assignee_id'])->toBeNull()
        ->and($activity->payload['to_assignee_name'])->toBeNull()
        ->and($activity->payload['from_assignee_name'])->toBe('Lan Nguyễn');
});

test('changing the assignee reads the previous value from a locked row, not the stale instance', function () {
    $actor = User::factory()->create();
    $original = User::factory()->create(['name' => 'Lan Nguyễn']);
    $concurrent = User::factory()->create(['name' => 'Hùng Trần']);
    $next = User::factory()->create(['name' => 'Dũng Phạm']);
    $task = Task::factory()->create(['assignee_id' => $original->id]);

    // Mô phỏng một request khác đã đổi người phụ trách trực tiếp trong DB sau khi
    // instance $task này được nạp (route-model-binding), mà không đụng vào $task.
    Task::query()->whereKey($task->id)->update(['assignee_id' => $concurrent->id]);

    expect($task->assignee_id)->toBe($original->id);

    app(UpdateTaskAction::class)->execute($actor, $task, [
        'assignee_id' => $next->id,
    ]);

    $activity = TaskActivity::query()->where('type', 'assigned')->sole();

    expect($activity->payload['from_assignee_id'])->toBe($concurrent->id)
        ->and($activity->payload['from_assignee_name'])->toBe('Hùng Trần')
        ->and($activity->payload['to_assignee_id'])->toBe($next->id);
});

test('updating a task without touching the assignee records no assignment activity', function () {
    $assignee = User::factory()->create();
    $task = Task::factory()->create(['assignee_id' => $assignee->id, 'title' => 'Cũ']);

    app(UpdateTaskAction::class)->execute(User::factory()->create(), $task, [
        'title' => 'Mới',
        'assignee_id' => $assignee->id,
    ]);

    expect(TaskActivity::query()->where('type', 'assigned')->count())->toBe(0)
        ->and($task->fresh()->title)->toBe('Mới');
});

test('updating the progress records an activity with the previous value', function () {
    $actor = User::factory()->create();
    $task = Task::factory()->create([
        'status' => TaskStatus::InProgress->value,
        'progress' => 40,
    ]);

    app(UpdateTaskProgressAction::class)->execute($actor, $task, 65);

    $activity = TaskActivity::query()->where('type', 'progress_updated')->sole();

    expect($activity->actor_id)->toBe($actor->id)
        ->and($activity->payload)->toBe(['from' => 40, 'to' => 65])
        ->and($task->fresh()->progress)->toBe(65);
});

test('re-submitting the same progress records no activity', function () {
    $task = Task::factory()->create([
        'status' => TaskStatus::InProgress->value,
        'progress' => 40,
    ]);

    app(UpdateTaskProgressAction::class)->execute(User::factory()->create(), $task, 40);

    expect(TaskActivity::query()->where('type', 'progress_updated')->count())->toBe(0);
});

test('commenting records an activity with a trimmed excerpt', function () {
    $author = User::factory()->create();
    $task = Task::factory()->create();
    $body = str_repeat('Nội dung trao đổi rất dài. ', 20);

    $comment = app(CreateTaskCommentAction::class)->execute($author, $task, $body);

    $activity = TaskActivity::query()->where('type', 'commented')->sole();

    expect($activity->actor_id)->toBe($author->id)
        ->and($activity->payload['comment_id'])->toBe($comment->id)
        ->and(mb_strlen($activity->payload['excerpt']))->toBeLessThanOrEqual(120)
        ->and($activity->payload['excerpt'])->toBe(mb_substr($body, 0, 119).'…');
});

test('uploading several files records a single activity for the request', function () {
    Storage::fake('local');

    $uploader = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        PermissionName::TaskComment->value,
    ]);
    $task = Task::factory()->create();

    $this->actingAs($uploader)->post(route('tasks.attachments.store', $task), [
        'files' => [
            UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
        ],
    ])->assertRedirect(route('tasks.show', $task));

    $activity = TaskActivity::query()->where('type', 'attachment_added')->sole();

    expect($activity->payload['file_count'])->toBe(3)
        ->and($activity->payload['original_names'])->toBe(['a.pdf', 'b.pdf', 'c.pdf'])
        ->and($activity->payload['attachment_ids'])->toHaveCount(3);
});

test('deleting an attachment records an activity that survives the soft delete', function () {
    Storage::fake('local');

    $uploader = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        PermissionName::TaskComment->value,
    ]);
    $task = Task::factory()->create();
    $attachment = TaskAttachment::factory()->for($task)->for($uploader, 'uploader')->create([
        'original_name' => 'báo cáo quý.pdf',
    ]);

    $this->actingAs($uploader)
        ->delete(route('tasks.attachments.destroy', [$task, $attachment]))
        ->assertRedirect(route('tasks.show', $task));

    $activity = TaskActivity::query()->where('type', 'attachment_removed')->sole();

    expect($activity->payload['attachment_id'])->toBe($attachment->id)
        ->and($activity->payload['original_name'])->toBe('báo cáo quý.pdf')
        ->and(TaskAttachment::query()->count())->toBe(0);
});

test('a failed upload leaves no orphaned activity', function () {
    Storage::fake('local');

    $uploader = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        PermissionName::TaskComment->value,
    ]);
    $task = Task::factory()->create();

    $this->actingAs($uploader)->post(route('tasks.attachments.store', $task), [
        'files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
    ])->assertSessionHasErrors('files.0');

    expect(TaskActivity::query()->where('type', 'attachment_added')->count())->toBe(0);
});

test('a transaction failure during upload leaves no orphaned activity or attachment row', function () {
    Storage::fake('local');

    $uploader = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        PermissionName::TaskComment->value,
    ]);
    $task = Task::factory()->create();

    // Kỹ thuật giống tests/Feature/Task/TaskAttachmentControllerTest.php
    // ("files already written to disk are discarded when the transaction fails after storing
    // them"): partial-mock DatabaseManager đứng sau facade DB (không phải final) để
    // DB::transaction() ném exception khi được gọi, mô phỏng lỗi DB thật sự xảy ra bên trong
    // khối transaction — không phải lỗi validation chặn trước khi Action chạy.
    $realDatabaseManager = app('db');
    $partialMock = Mockery::mock($realDatabaseManager)->makePartial();
    $partialMock->shouldReceive('transaction')->once()->andThrow(new RuntimeException('Lỗi giao dịch DB giả lập.'));
    $this->app->instance('db', $partialMock);
    DB::clearResolvedInstance('db');

    $this->withoutExceptionHandling();

    $caught = null;

    try {
        $this->actingAs($uploader)->post(route('tasks.attachments.store', $task), [
            'files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ]);
    } catch (RuntimeException $exception) {
        $caught = $exception;
    }

    expect($caught)->not->toBeNull()
        ->and($caught->getMessage())->toBe('Lỗi giao dịch DB giả lập.');

    expect(TaskActivity::query()->where('type', 'attachment_added')->count())->toBe(0)
        ->and(TaskAttachment::query()->count())->toBe(0);
});

test('rows already inserted inside the transaction are rolled back when recording the activity fails', function () {
    Storage::fake('local');

    $uploader = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        PermissionName::TaskComment->value,
    ]);
    $task = Task::factory()->create();

    // Kỹ thuật khác: mock transaction() ở test trên khiến closure không bao giờ chạy, nên
    // count() === 0 chỉ chứng minh "chưa từng ghi gì", không chứng minh rollback thật. Ở đây,
    // dùng model event `creating` trên TaskActivity để ném lỗi ở bước CUỐI của closure —
    // sau khi các bản ghi TaskAttachment và AuditLog đã thực sự được insert bên trong cùng
    // transaction. Khi exception ném ra, transaction rollback toàn bộ, xoá cả những gì đã ghi.
    TaskActivity::creating(static function (): void {
        throw new RuntimeException('Lỗi giả lập sau khi đã ghi attachment.');
    });

    try {
        expect(fn () => app(StoreTaskAttachmentAction::class)->execute($uploader, $task, [
            UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ]))->toThrow(RuntimeException::class, 'Lỗi giả lập sau khi đã ghi attachment.');

        expect(TaskAttachment::query()->count())->toBe(0)
            ->and(AuditLog::query()->count())->toBe(0)
            ->and(TaskActivity::query()->count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    } finally {
        TaskActivity::flushEventListeners();
    }
});

test('task details expose paginated activities newest first', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
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
    $viewer = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
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
    $viewer = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $task = Task::factory()->create();

    foreach (range(1, 10) as $index) {
        TaskActivity::factory()->for($task)->for(User::factory()->create(), 'actor')->create();
    }

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->actingAs($viewer)->get(route('tasks.show', $task))->assertOk();

    // Đo thực nghiệm trên trang này (2026-07-29): eager load đúng với 10 activity / 10 actor
    // khác nhau tốn đúng 10 query. Khi giả lập N+1 thật (lazy-load actor trong vòng lặp thay vì
    // ->with('actor:...')) con số nhảy lên 19. Ngưỡng 15 chừa vài query dôi ra cho các truy vấn
    // cố định của trang (session, permission, task, comments, attachments...) mà không rơi vào
    // vùng 19 của N+1 thật — đủ chặt để bắt lỗi N+1 quay lại, đủ rộng để không giòn.
    expect($queries)->toBeLessThan(15);
});

test('the task page no longer sends status histories', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $task = Task::factory()->create();

    $this->actingAs($viewer)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->missing('task.status_histories'));
});
