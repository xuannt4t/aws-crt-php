<?php

use App\Enums\PermissionName;
use App\Models\OrganizationUnit;
use App\Models\User;

test('organization view permission controls access to organization units', function () {
    $viewer = userWithPermissions([PermissionName::OrganizationView->value]);
    $userWithoutPermission = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();

    expect($viewer->can('viewAny', OrganizationUnit::class))->toBeTrue()
        ->and($viewer->can('view', $unit))->toBeTrue()
        ->and($userWithoutPermission->can('viewAny', OrganizationUnit::class))->toBeFalse()
        ->and($userWithoutPermission->can('view', $unit))->toBeFalse();
});

test('organization mutation permissions are checked independently', function () {
    $creator = userWithPermissions([PermissionName::OrganizationCreate->value]);
    $updater = userWithPermissions([PermissionName::OrganizationUpdate->value]);
    $deleter = userWithPermissions([PermissionName::OrganizationDelete->value]);
    $legacyAdmin = User::factory()->create(['is_system_admin' => true]);
    $unit = OrganizationUnit::factory()->create();

    expect($creator->can('create', OrganizationUnit::class))->toBeTrue()
        ->and($creator->can('update', $unit))->toBeFalse()
        ->and($updater->can('update', $unit))->toBeTrue()
        ->and($updater->can('delete', $unit))->toBeFalse()
        ->and($deleter->can('delete', $unit))->toBeTrue()
        ->and($legacyAdmin->can('create', OrganizationUnit::class))->toBeFalse();
});
