<?php

use App\Models\OrganizationUnit;
use App\Models\User;

test('a system admin can view the users index', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);

    $response = $this->actingAs($admin)->get(route('users.index'));

    $response->assertOk();
});

test('a system admin can create a user', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'organization_unit_id' => $unit->id,
        'name' => 'New Employee',
        'email' => 'new.employee@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'is_system_admin' => false,
    ]);

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('users', ['email' => 'new.employee@example.com']);
});

test('a regular user cannot create a user', function () {
    $user = User::factory()->create(['is_system_admin' => false]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($user)->post(route('users.store'), [
        'organization_unit_id' => $unit->id,
        'name' => 'New Employee',
        'email' => 'new.employee@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('users', ['email' => 'new.employee@example.com']);
});

test('a system admin can update a user', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $target = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->put(route('users.update', $target), [
        'organization_unit_id' => $unit->id,
        'name' => 'Renamed User',
        'email' => $target->email,
        'is_system_admin' => false,
    ]);

    $response->assertRedirect(route('users.index'));
    expect($target->fresh()->name)->toBe('Renamed User');
});

test('a system admin can disable and re-enable a user', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $target = User::factory()->create(['is_active' => true]);

    $this->actingAs($admin)->patch(route('users.disable', $target))
        ->assertRedirect(route('users.index'));
    expect($target->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->patch(route('users.enable', $target))
        ->assertRedirect(route('users.index'));
    expect($target->fresh()->is_active)->toBeTrue();
});

test('a disabled user cannot log in', function () {
    $user = User::factory()->create(['is_active' => false]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});
