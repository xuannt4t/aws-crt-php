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
