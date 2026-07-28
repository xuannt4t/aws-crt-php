<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\OrganizationUnit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

test('a user with permission can view the users index', function () {
    $admin = userWithPermissions([PermissionName::UserView->value]);

    $response = $this->actingAs($admin)->get(route('users.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions.0', PermissionName::UserView->value));
});

test('a user with permission can create a user with the default employee role', function () {
    $admin = userWithPermissions([PermissionName::UserCreate->value]);
    $unit = OrganizationUnit::factory()->create();
    Role::findOrCreate(RoleName::Employee->value, 'web');

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'organization_unit_id' => $unit->id,
        'name' => 'New Employee',
        'email' => 'new.employee@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('users', ['email' => 'new.employee@example.com']);
    expect(User::where('email', 'new.employee@example.com')->firstOrFail()->hasRole(RoleName::Employee->value))
        ->toBeTrue();

    $auditLog = AuditLog::where('action', AuditAction::UserCreated->value)->firstOrFail();

    expect($auditLog->actor_id)->toBe($admin->id)
        ->and($auditLog->after_values['roles'])->toBe([RoleName::Employee->value])
        ->and($auditLog->after_values)->not->toHaveKey('password');
});

test('a user with assign role permission can choose roles for a new user', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = userWithPermissions([
        PermissionName::UserCreate->value,
        PermissionName::UserAssignRole->value,
    ]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'organization_unit_id' => $unit->id,
        'name' => 'New Director',
        'email' => 'new.director@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roles' => [RoleName::Director->value],
    ]);

    $response->assertRedirect(route('users.index'));

    expect(User::where('email', 'new.director@example.com')->firstOrFail()->hasRole(RoleName::Director->value))
        ->toBeTrue();
});

test('a user without assign role permission cannot choose roles', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = userWithPermissions([PermissionName::UserCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'organization_unit_id' => $unit->id,
        'name' => 'Unauthorized Director',
        'email' => 'unauthorized.director@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roles' => [RoleName::Director->value],
    ]);

    $response->assertSessionHasErrors('roles');
    $this->assertDatabaseMissing('users', ['email' => 'unauthorized.director@example.com']);
});

test('a regular user cannot create a user', function () {
    $user = User::factory()->create();
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

test('a user with permission can update a user', function () {
    $admin = userWithPermissions([PermissionName::UserUpdate->value]);
    $target = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->put(route('users.update', $target), [
        'organization_unit_id' => $unit->id,
        'name' => 'Renamed User',
        'email' => $target->email,
    ]);

    $response->assertRedirect(route('users.index'));
    expect($target->fresh()->name)->toBe('Renamed User');
    expect(AuditLog::where('action', AuditAction::UserUpdated->value)->exists())->toBeTrue();
});

test('a user with assign role permission can update another users roles', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = userWithPermissions([
        PermissionName::UserUpdate->value,
        PermissionName::UserAssignRole->value,
    ]);
    $target = User::factory()->create();
    $target->assignRole(RoleName::Employee->value);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->put(route('users.update', $target), [
        'organization_unit_id' => $unit->id,
        'name' => $target->name,
        'email' => $target->email,
        'roles' => [RoleName::Director->value],
    ]);

    $response->assertRedirect(route('users.index'));

    $auditLog = AuditLog::where('action', AuditAction::UserRolesUpdated->value)->firstOrFail();

    expect($target->fresh()->hasRole(RoleName::Director->value))->toBeTrue()
        ->and($auditLog->before_values['roles'])->toBe([RoleName::Employee->value])
        ->and($auditLog->after_values['roles'])->toBe([RoleName::Director->value]);
});

test('a user with permission can disable and re-enable a user', function () {
    $admin = userWithPermissions([PermissionName::UserDisable->value]);
    $target = User::factory()->create(['is_active' => true]);

    $this->actingAs($admin)->patch(route('users.disable', $target))
        ->assertRedirect(route('users.index'));
    expect($target->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->patch(route('users.enable', $target))
        ->assertRedirect(route('users.index'));
    expect($target->fresh()->is_active)->toBeTrue();
    expect(AuditLog::whereIn('action', [
        AuditAction::UserDisabled->value,
        AuditAction::UserEnabled->value,
    ])->count())->toBe(2);
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

test('a user with permission can soft-delete a user', function () {
    $admin = userWithPermissions([PermissionName::UserDisable->value]);
    $target = User::factory()->create();

    $response = $this->actingAs($admin)->delete(route('users.destroy', $target));

    $response->assertRedirect(route('users.index'));
    $this->assertSoftDeleted($target);
    expect(AuditLog::where('action', AuditAction::UserDeleted->value)->exists())->toBeTrue();
});

test('a regular user cannot delete a user', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $response = $this->actingAs($user)->delete(route('users.destroy', $target));

    $response->assertForbidden();
    $this->assertNotSoftDeleted($target);
});

test('a regular user cannot update a user', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($user)->put(route('users.update', $target), [
        'organization_unit_id' => $unit->id,
        'name' => 'Renamed User',
        'email' => $target->email,
    ]);

    $response->assertForbidden();
});

test('creating a user requires a unique email', function () {
    $admin = userWithPermissions([PermissionName::UserCreate->value]);
    $unit = OrganizationUnit::factory()->create();
    $existing = User::factory()->create();

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'organization_unit_id' => $unit->id,
        'name' => 'New Employee',
        'email' => $existing->email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
});

test('creating a user requires an organization unit', function () {
    $admin = userWithPermissions([PermissionName::UserCreate->value]);

    $response = $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'New Employee',
        'email' => 'new.employee@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('organization_unit_id');
});

test('a user cannot disable themselves', function () {
    $admin = userWithPermissions([PermissionName::UserDisable->value], ['is_active' => true]);

    $response = $this->actingAs($admin)->patch(route('users.disable', $admin));

    $response->assertForbidden();
    expect($admin->fresh()->is_active)->toBeTrue();
});

test('a user cannot delete themselves', function () {
    $admin = userWithPermissions([PermissionName::UserDisable->value]);

    $response = $this->actingAs($admin)->delete(route('users.destroy', $admin));

    $response->assertForbidden();
    $this->assertNotSoftDeleted($admin);
});

test('a system admin cannot remove their own system admin role', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::SystemAdmin->value);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($admin)->put(route('users.update', $admin), [
        'organization_unit_id' => $unit->id,
        'name' => $admin->name,
        'email' => $admin->email,
        'roles' => [RoleName::Employee->value],
    ]);

    $response->assertSessionHasErrors('roles');
    expect($admin->fresh()->hasRole(RoleName::SystemAdmin->value))->toBeTrue();
});
