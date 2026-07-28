<?php

use App\Enums\PermissionName;
use App\Models\Task;
use App\Models\TaskAttachment;

test('attaching requires both view and comment permissions', function (array $permissions, bool $expected) {
    $user = userWithPermissions($permissions);
    $task = Task::factory()->create();

    expect($user->can('attach', $task))->toBe($expected);
})->with([
    'view and comment' => [[PermissionName::TaskView->value, PermissionName::TaskComment->value], true],
    'view only' => [[PermissionName::TaskView->value], false],
    'comment only' => [[PermissionName::TaskComment->value], false],
    'none' => [[], false],
]);

test('downloading an attachment requires the task view permission', function (array $permissions, bool $expected) {
    $user = userWithPermissions($permissions);
    $task = Task::factory()->create();

    expect($user->can('downloadAttachment', $task))->toBe($expected);
})->with([
    'view' => [[PermissionName::TaskView->value], true],
    'none' => [[], false],
]);

test('the uploader can delete their own attachment', function () {
    $uploader = userWithPermissions([PermissionName::TaskView->value]);
    $attachment = TaskAttachment::factory()->for($uploader, 'uploader')->create();

    expect($uploader->can('delete', $attachment))->toBeTrue();
});

test('a user with the task update permission can delete any attachment', function () {
    $manager = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskUpdate->value,
    ]);
    $attachment = TaskAttachment::factory()->create();

    expect($manager->can('delete', $attachment))->toBeTrue();
});

test('another user without the task update permission cannot delete an attachment', function () {
    $other = userWithPermissions([PermissionName::TaskView->value]);
    $attachment = TaskAttachment::factory()->create();

    expect($other->can('delete', $attachment))->toBeFalse();
});
