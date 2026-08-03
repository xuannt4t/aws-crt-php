<?php

use App\Enums\PermissionName;
use App\Models\TaskRecurrence;
use App\Models\User;

test('viewAny and view require task view permission', function () {
    // "view" cần cả cổng task.view lẫn phạm vi (spec §3.1); viewer ở đây giữ
    // task.view_all nên phạm vi không giới hạn mẫu ngẫu nhiên bên dưới.
    $viewer = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $outsider = User::factory()->create();
    $recurrence = TaskRecurrence::factory()->create();

    expect($viewer->can('viewAny', TaskRecurrence::class))->toBeTrue()
        ->and($outsider->can('viewAny', TaskRecurrence::class))->toBeFalse()
        ->and($viewer->can('view', $recurrence))->toBeTrue()
        ->and($outsider->can('view', $recurrence))->toBeFalse();
});

test('create requires task create permission', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $outsider = User::factory()->create();

    expect($creator->can('create', TaskRecurrence::class))->toBeTrue()
        ->and($outsider->can('create', TaskRecurrence::class))->toBeFalse();
});

test('update requires task update permission and either being the creator or having task assign', function () {
    $creatorUser = User::factory()->create();
    grantPermissions($creatorUser, [PermissionName::TaskUpdate->value]);
    $recurrence = TaskRecurrence::factory()->create(['creator_id' => $creatorUser->id]);

    // Người không phải người tạo còn phải nằm trong phạm vi dữ liệu của mẫu,
    // nên giữ thêm task.view_all; nếu thiếu phạm vi thì bị từ chối.
    $assignerWithUpdate = userWithPermissions([
        PermissionName::TaskUpdate->value,
        PermissionName::TaskAssign->value,
        PermissionName::TaskViewAll->value,
    ]);

    $assignerOutOfScope = userWithPermissions([
        PermissionName::TaskUpdate->value,
        PermissionName::TaskAssign->value,
    ]);

    $updateOnlyNonCreator = userWithPermissions([PermissionName::TaskUpdate->value]);

    expect($creatorUser->can('update', $recurrence))->toBeTrue()
        ->and($assignerWithUpdate->can('update', $recurrence))->toBeTrue()
        ->and($assignerOutOfScope->can('update', $recurrence))->toBeFalse()
        ->and($updateOnlyNonCreator->can('update', $recurrence))->toBeFalse();
});

test('a creator without task update permission cannot update their own template', function () {
    $creator = User::factory()->create();
    $recurrence = TaskRecurrence::factory()->create(['creator_id' => $creator->id]);

    expect($creator->can('update', $recurrence))->toBeFalse();
});

test('delete requires task delete permission and the record being in scope', function () {
    $deleter = userWithPermissions([PermissionName::TaskDelete->value, PermissionName::TaskViewAll->value]);
    $deleterOutOfScope = userWithPermissions([PermissionName::TaskDelete->value]);
    $recurrence = TaskRecurrence::factory()->create();
    $outsider = User::factory()->create();

    expect($deleter->can('delete', $recurrence))->toBeTrue()
        ->and($deleterOutOfScope->can('delete', $recurrence))->toBeFalse()
        ->and($outsider->can('delete', $recurrence))->toBeFalse();
});

test('toggle follows the same rule as update', function () {
    $creator = User::factory()->create();
    grantPermissions($creator, [PermissionName::TaskUpdate->value]);
    $recurrence = TaskRecurrence::factory()->create(['creator_id' => $creator->id]);

    $assigner = userWithPermissions([
        PermissionName::TaskUpdate->value,
        PermissionName::TaskAssign->value,
        PermissionName::TaskViewAll->value,
    ]);

    $updateOnlyNonCreator = userWithPermissions([PermissionName::TaskUpdate->value]);

    expect($creator->can('toggle', $recurrence))->toBeTrue()
        ->and($assigner->can('toggle', $recurrence))->toBeTrue()
        ->and($updateOnlyNonCreator->can('toggle', $recurrence))->toBeFalse();
});
