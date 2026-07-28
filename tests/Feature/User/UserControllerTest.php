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

test('a system admin can soft-delete a user', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $target = User::factory()->create();

    $response = $this->actingAs($admin)->delete(route('users.destroy', $target));

    $response->assertRedirect(route('users.index'));
    $this->assertSoftDeleted($target);
});

test('a regular user cannot delete a user', function () {
    $user = User::factory()->create(['is_system_admin' => false]);
    $target = User::factory()->create();

    $response = $this->actingAs($user)->delete(route('users.destroy', $target));

    $response->assertForbidden();
    $this->assertNotSoftDeleted($target);
});

test('a regular user cannot update a user', function () {
    $user = User::factory()->create(['is_system_admin' => false]);
    $target = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($user)->put(route('users.update', $target), [
        'organization_unit_id' => $unit->id,
        'name' => 'Renamed User',
        'email' => $target->email,
        'is_system_admin' => false,
    ]);

    $response->assertForbidden();
});

test('creating a user requires a unique email', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $unit = OrganizationUnit::factory()->create();
    $existing = User::factory()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'organization_unit_id' => $unit->id,
        'name' => 'New Employee',
        'email' => $existing->email,
        'password' => 'password',
        'password_confirmation' => 'password',
        'is_system_admin' => false,
    ]);

    $response->assertSessionHasErrors('email');
});

test('creating a user requires an organization unit', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'New Employee',
        'email' => 'new.employee@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'is_system_admin' => false,
    ]);

    $response->assertSessionHasErrors('organization_unit_id');
});

test('an admin cannot disable themselves', function () {
    $admin = User::factory()->create(['is_system_admin' => true, 'is_active' => true]);

    $response = $this->actingAs($admin)->patch(route('users.disable', $admin));

    $response->assertForbidden();
    expect($admin->fresh()->is_active)->toBeTrue();
});

test('an admin cannot delete themselves', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);

    $response = $this->actingAs($admin)->delete(route('users.destroy', $admin));

    $response->assertForbidden();
    $this->assertNotSoftDeleted($admin);
});

test('an admin cannot demote themselves via update', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->put(route('users.update', $admin), [
        'organization_unit_id' => $unit->id,
        'name' => $admin->name,
        'email' => $admin->email,
        'is_system_admin' => false,
    ]);

    $response->assertSessionHasErrors('is_system_admin');
    expect($admin->fresh()->is_system_admin)->toBeTrue();
});
