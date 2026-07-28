<?php

use App\Models\OrganizationUnit;
use App\Models\User;

test('any authenticated user can view organization units', function () {
    $user = User::factory()->create(['is_system_admin' => false]);
    $unit = OrganizationUnit::factory()->create();

    expect($user->can('viewAny', OrganizationUnit::class))->toBeTrue();
    expect($user->can('view', $unit))->toBeTrue();
});

test('only a system admin can create, update or delete organization units', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $regular = User::factory()->create(['is_system_admin' => false]);
    $unit = OrganizationUnit::factory()->create();

    expect($admin->can('create', OrganizationUnit::class))->toBeTrue();
    expect($admin->can('update', $unit))->toBeTrue();
    expect($admin->can('delete', $unit))->toBeTrue();

    expect($regular->can('create', OrganizationUnit::class))->toBeFalse();
    expect($regular->can('update', $unit))->toBeFalse();
    expect($regular->can('delete', $unit))->toBeFalse();
});
