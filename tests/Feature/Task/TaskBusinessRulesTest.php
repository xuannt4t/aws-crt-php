<?php

use App\Actions\Task\UpdateTaskAction;
use App\Models\Task;
use Illuminate\Validation\ValidationException;

test('a task cannot use one of its descendants as parent', function () {
    $grandparent = Task::factory()->create();
    $parent = Task::factory()->create(['parent_id' => $grandparent->id]);
    $child = Task::factory()->create(['parent_id' => $parent->id]);

    expect(fn () => app(UpdateTaskAction::class)->execute($grandparent, [
        'parent_id' => $child->id,
    ]))->toThrow(ValidationException::class);

    expect($grandparent->fresh()->parent_id)->toBeNull();
});

test('a task can move below an unrelated parent', function () {
    $task = Task::factory()->create();
    $newParent = Task::factory()->create();

    app(UpdateTaskAction::class)->execute($task, ['parent_id' => $newParent->id]);

    expect($task->fresh()->parent_id)->toBe($newParent->id);
});
