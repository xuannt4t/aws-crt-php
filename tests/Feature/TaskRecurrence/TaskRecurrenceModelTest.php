<?php

use App\Models\Task;
use App\Models\TaskRecurrence;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

test('a task recurrence template has generated tasks and a task belongs to its recurrence', function () {
    $recurrence = TaskRecurrence::factory()->create();
    $older = Task::factory()->create([
        'task_recurrence_id' => $recurrence->id,
        'recurrence_date' => '2026-07-01',
    ]);
    $newer = Task::factory()->create([
        'task_recurrence_id' => $recurrence->id,
        'recurrence_date' => '2026-08-01',
    ]);

    expect($recurrence->tasks->pluck('id')->all())->toBe([$newer->id, $older->id])
        ->and($newer->recurrence->is($recurrence))->toBeTrue();
});

test('weekdays is cast to an array and recurrence_date is cast to a date', function () {
    $recurrence = TaskRecurrence::factory()->weekly([2, 4])->create();
    $task = Task::factory()->create([
        'task_recurrence_id' => $recurrence->id,
        'recurrence_date' => '2026-08-03',
    ]);

    expect($recurrence->weekdays)->toBeArray()
        ->and($recurrence->weekdays)->toBe([2, 4])
        ->and($task->recurrence_date)->toBeInstanceOf(Carbon::class)
        ->and($task->recurrence_date->toDateString())->toBe('2026-08-03');
});

test('the unique constraint on task_recurrence_id and recurrence_date rejects a duplicate period', function () {
    $recurrence = TaskRecurrence::factory()->create();
    Task::factory()->create([
        'task_recurrence_id' => $recurrence->id,
        'recurrence_date' => '2026-08-03',
    ]);

    expect(fn () => Task::factory()->create([
        'task_recurrence_id' => $recurrence->id,
        'recurrence_date' => '2026-08-03',
    ]))->toThrow(QueryException::class);
});

test('ordinary non recurring tasks are unaffected by the unique constraint', function () {
    $first = Task::factory()->create([
        'task_recurrence_id' => null,
        'recurrence_date' => null,
    ]);
    $second = Task::factory()->create([
        'task_recurrence_id' => null,
        'recurrence_date' => null,
    ]);

    expect($first->exists)->toBeTrue()
        ->and($second->exists)->toBeTrue();
});

test('a soft deleted recurrence template is still readable from its generated tasks', function () {
    $recurrence = TaskRecurrence::factory()->create();
    $task = Task::factory()->create([
        'task_recurrence_id' => $recurrence->id,
        'recurrence_date' => '2026-08-03',
    ]);

    $recurrence->delete();

    $this->assertSoftDeleted($recurrence);
    expect($task->fresh()->recurrence->is($recurrence))->toBeTrue();
});
