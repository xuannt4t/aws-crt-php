<?php

use App\Enums\PermissionName;
use App\Models\Task;
use App\Models\User;

test('task permissions control each policy ability', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    // Mọi ability chạm tới một bản ghi cụ thể đều đòi thêm phạm vi dữ liệu, nên
    // các actor bên dưới giữ task.view_all để nằm trong phạm vi của $task.
    $updater = userWithPermissions([PermissionName::TaskUpdate->value, PermissionName::TaskViewAll->value]);
    $deleter = userWithPermissions([PermissionName::TaskDelete->value, PermissionName::TaskViewAll->value]);
    $assigner = userWithPermissions([PermissionName::TaskAssign->value, PermissionName::TaskViewAll->value]);
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

test('commenting on a task requires both view and comment permissions', function () {
    $commenter = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        PermissionName::TaskComment->value,
    ]);
    $commentOnly = userWithPermissions([PermissionName::TaskComment->value]);
    $task = Task::factory()->create();

    expect($commenter->can('comment', $task))->toBeTrue()
        ->and($commentOnly->can('comment', $task))->toBeFalse();
});

test('only the assignee can run task workflow actions with the required permission', function () {
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
        ->and($assignee->can('recall', $task))->toBeTrue()
        ->and($otherUser->can('start', $task))->toBeFalse()
        ->and($otherUser->can('updateProgress', $task))->toBeFalse()
        ->and($otherUser->can('submit', $task))->toBeFalse()
        ->and($otherUser->can('recall', $task))->toBeFalse();
});
