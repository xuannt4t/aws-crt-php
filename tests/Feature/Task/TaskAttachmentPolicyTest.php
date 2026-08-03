<?php

use App\Enums\PermissionName;
use App\Models\Task;
use App\Models\TaskAttachment;

test('attaching requires both view and comment permissions', function (array $permissions, bool $expected) {
    $user = userWithPermissions($permissions);
    $task = Task::factory()->create();

    expect($user->can('attach', $task))->toBe($expected);
})->with([
    // Các bộ "true" giữ thêm task.view_all vì attach/downloadAttachment nay
    // đòi công việc nằm trong phạm vi dữ liệu của người dùng.
    'view and comment' => [[PermissionName::TaskView->value, PermissionName::TaskViewAll->value, PermissionName::TaskComment->value], true],
    'view only' => [[PermissionName::TaskView->value], false],
    'comment only' => [[PermissionName::TaskComment->value], false],
    'none' => [[], false],
]);

test('downloading an attachment requires the task view permission', function (array $permissions, bool $expected) {
    $user = userWithPermissions($permissions);
    $task = Task::factory()->create();

    expect($user->can('downloadAttachment', $task))->toBe($expected);
})->with([
    'view' => [[PermissionName::TaskView->value, PermissionName::TaskViewAll->value], true],
    'view without scope' => [[PermissionName::TaskView->value], false],
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

test('the uploader cannot delete their own attachment without the task view permission', function () {
    $uploader = userWithPermissions([]);
    $attachment = TaskAttachment::factory()->for($uploader, 'uploader')->create();

    expect($uploader->can('delete', $attachment))->toBeFalse();
});

test('a user with only the task update permission cannot delete an attachment without the task view permission', function () {
    $manager = userWithPermissions([PermissionName::TaskUpdate->value]);
    $attachment = TaskAttachment::factory()->create();

    expect($manager->can('delete', $attachment))->toBeFalse();
});
