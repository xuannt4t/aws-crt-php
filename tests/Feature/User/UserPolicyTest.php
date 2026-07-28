<?php

use App\Enums\PermissionName;
use App\Models\User;

test('user view permission controls access to the user directory', function () {
    $viewer = userWithPermissions([PermissionName::UserView->value]);
    $userWithoutPermission = User::factory()->create();
    $target = User::factory()->create();

    expect($viewer->can('viewAny', User::class))->toBeTrue()
        ->and($viewer->can('view', $target))->toBeTrue()
        ->and($userWithoutPermission->can('viewAny', User::class))->toBeFalse()
        ->and($userWithoutPermission->can('view', $target))->toBeFalse();
});

test('user mutation permissions are checked independently', function () {
    $creator = userWithPermissions([PermissionName::UserCreate->value]);
    $updater = userWithPermissions([PermissionName::UserUpdate->value]);
    $disabler = userWithPermissions([PermissionName::UserDisable->value]);
    $legacyAdmin = User::factory()->create(['is_system_admin' => true]);
    $target = User::factory()->create();

    expect($creator->can('create', User::class))->toBeTrue()
        ->and($creator->can('update', $target))->toBeFalse()
        ->and($updater->can('update', $target))->toBeTrue()
        ->and($updater->can('disable', $target))->toBeFalse()
        ->and($disabler->can('disable', $target))->toBeTrue()
        ->and($disabler->can('delete', $target))->toBeTrue()
        ->and($legacyAdmin->can('create', User::class))->toBeFalse();
});

test('a user cannot disable or delete themselves even with permission', function () {
    $user = userWithPermissions([PermissionName::UserDisable->value]);

    expect($user->can('disable', $user))->toBeFalse()
        ->and($user->can('delete', $user))->toBeFalse();
});
