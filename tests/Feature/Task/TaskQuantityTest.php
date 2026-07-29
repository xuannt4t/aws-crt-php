<?php

use App\Actions\Task\CreateTaskAction;
use App\Actions\Task\UpdateTaskAction;
use App\Actions\Task\UpdateTaskActualQuantityAction;
use App\Actions\Task\UpdateTaskProgressAction;
use App\Enums\TaskActivityType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\OrganizationUnit;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Validation\ValidationException;

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
