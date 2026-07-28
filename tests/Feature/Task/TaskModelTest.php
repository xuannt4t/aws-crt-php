<?php

use App\Enums\TaskStatus;
use App\Models\OrganizationUnit;
use App\Models\Task;
use App\Models\User;

test('a task belongs to its organization creator assignee and parent', function () {
    $unit = OrganizationUnit::factory()->create();
    $creator = User::factory()->create();
    $assignee = User::factory()->create();
    $parent = Task::factory()->create();
    $task = Task::factory()->create([
        'organization_unit_id' => $unit->id,
        'creator_id' => $creator->id,
        'assignee_id' => $assignee->id,
        'parent_id' => $parent->id,
    ]);

    expect($task->organizationUnit->is($unit))->toBeTrue()
        ->and($task->creator->is($creator))->toBeTrue()
        ->and($task->assignee->is($assignee))->toBeTrue()
        ->and($task->parent->is($parent))->toBeTrue();
});

test('overdue is derived from due date and terminal status', function () {
    $overdue = Task::factory()->create([
        'status' => TaskStatus::InProgress,
        'due_at' => now()->subHour(),
    ]);
    $completed = Task::factory()->create([
        'status' => TaskStatus::Completed,
        'due_at' => now()->subHour(),
    ]);

    expect($overdue->isOverdue())->toBeTrue()
        ->and($completed->isOverdue())->toBeFalse()
        ->and(Task::query()->overdue()->pluck('id')->all())->toBe([$overdue->id]);
});

test('a task is soft deleted', function () {
    $task = Task::factory()->create();

    $task->delete();

    $this->assertSoftDeleted($task);
});
