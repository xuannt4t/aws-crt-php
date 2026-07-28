<?php

use App\Models\User;

test('any authenticated user can view the user directory', function () {
    $user = User::factory()->create(['is_system_admin' => false]);
    $target = User::factory()->create();

    expect($user->can('viewAny', User::class))->toBeTrue();
    expect($user->can('view', $target))->toBeTrue();
});

test('only a system admin can create, update, disable or delete users', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $regular = User::factory()->create(['is_system_admin' => false]);
    $target = User::factory()->create();

    expect($admin->can('create', User::class))->toBeTrue();
    expect($admin->can('update', $target))->toBeTrue();
    expect($admin->can('disable', $target))->toBeTrue();
    expect($admin->can('delete', $target))->toBeTrue();

    expect($regular->can('create', User::class))->toBeFalse();
    expect($regular->can('update', $target))->toBeFalse();
    expect($regular->can('disable', $target))->toBeFalse();
    expect($regular->can('delete', $target))->toBeFalse();
});
