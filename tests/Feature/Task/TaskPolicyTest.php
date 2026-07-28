<?php

use App\Enums\PermissionName;
use App\Models\Task;
use App\Models\User;

test('task permissions control each policy ability', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $updater = userWithPermissions([PermissionName::TaskUpdate->value]);
    $deleter = userWithPermissions([PermissionName::TaskDelete->value]);
    $assigner = userWithPermissions([PermissionName::TaskAssign->value]);
    $userWithoutPermission = User::factory()->create();
    $task = Task::factory()->create();

    expect($viewer->can('viewAny', Task::class))->toBeTrue()
        ->and($viewer->can('view', $task))->toBeTrue()
        ->and($creator->can('create', Task::class))->toBeTrue()
        ->and($updater->can('update', $task))->toBeTrue()
        ->and($deleter->can('delete', $task))->toBeTrue()
        ->and($assigner->can('assign', $task))->toBeTrue()
        ->and($userWithoutPermission->can('viewAny', Task::class))->toBeFalse();
});

test('only the assignee can start and submit a task with the required permission', function () {
    $assignee = userWithPermissions([
        PermissionName::TaskUpdate->value,
        PermissionName::TaskSubmit->value,
    ]);
    $otherUser = userWithPermissions([
        PermissionName::TaskUpdate->value,
        PermissionName::TaskSubmit->value,
    ]);
    $task = Task::factory()->create(['assignee_id' => $assignee->id]);

    expect($assignee->can('start', $task))->toBeTrue()
        ->and($assignee->can('updateProgress', $task))->toBeTrue()
        ->and($assignee->can('submit', $task))->toBeTrue()
        ->and($otherUser->can('start', $task))->toBeFalse()
        ->and($otherUser->can('updateProgress', $task))->toBeFalse()
        ->and($otherUser->can('submit', $task))->toBeFalse();
});
