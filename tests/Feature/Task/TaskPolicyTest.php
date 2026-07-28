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
