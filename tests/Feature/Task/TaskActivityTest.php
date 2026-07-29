<?php

use App\Actions\Task\CreateTaskAction;
use App\Actions\Task\CreateTaskCommentAction;
use App\Actions\Task\RecordTaskActivityAction;
use App\Actions\Task\TransitionTaskStatusAction;
use App\Actions\Task\UpdateTaskAction;
use App\Actions\Task\UpdateTaskProgressAction;
use App\Enums\PermissionName;
use App\Enums\TaskActivityType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
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
        ->and(mb_strlen($activity->payload['excerpt']))->toBeLessThanOrEqual(121);
});

test('uploading several files records a single activity for the request', function () {
    Storage::fake('local');

    $uploader = userWithPermissions([
        PermissionName::TaskView->value,
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
