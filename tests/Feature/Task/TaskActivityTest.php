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
