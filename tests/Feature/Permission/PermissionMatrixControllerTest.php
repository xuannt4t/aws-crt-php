<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

test('a user without system.manage_settings cannot view the permission matrix', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('permission-matrix.index'));

    $response->assertForbidden();
});

test('a user with system.manage_settings can view the permission matrix with the expected props', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = userWithPermissions([PermissionName::SystemManageSettings->value]);

    $response = $this->actingAs($admin)->get(route('permission-matrix.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('roles', count(RoleName::cases()))
            ->has('permissionGroups')
            ->has('matrix')
            ->where('lockedRoles', [RoleName::SystemAdmin->value])
            ->where('matrix.'.RoleName::Employee->value, fn ($permissions) => collect($permissions)
                ->contains(PermissionName::TaskViewOwn->value)));
});

test('a user without system.manage_settings cannot update the permission matrix', function () {
    $this->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('permission-matrix.update'), [
        'matrix' => [
            RoleName::Employee->value => [PermissionName::TaskView->value],
        ],
    ]);

    $response->assertForbidden();
});

test('updating the permission matrix syncs permissions and records an audit entry only for changed roles', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = userWithPermissions([PermissionName::SystemManageSettings->value]);

    $employeeRole = Role::findByName(RoleName::Employee->value, 'web');
    $unchangedPermissions = $employeeRole->permissions->pluck('name')->sort()->values()->all();
    $changedPermissions = [...$unchangedPermissions, PermissionName::TaskExport->value];

    $projectManagerRole = Role::findByName(RoleName::ProjectManager->value, 'web');
    $projectManagerPermissions = $projectManagerRole->permissions->pluck('name')->sort()->values()->all();

    $response = $this->actingAs($admin)->put(route('permission-matrix.update'), [
        'matrix' => [
            RoleName::Employee->value => $changedPermissions,
            RoleName::ProjectManager->value => $projectManagerPermissions,
        ],
    ]);

    $response->assertRedirect(route('permission-matrix.index'));

    expect($employeeRole->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect($changedPermissions)->sort()->values()->all());

    expect(AuditLog::where('action', AuditAction::RolePermissionsUpdated->value)->count())->toBe(1);

    $auditLog = AuditLog::where('action', AuditAction::RolePermissionsUpdated->value)->firstOrFail();

    expect($auditLog->subject_id)->toBe($employeeRole->id)
        ->and($auditLog->before_values['permissions'])->toBe($unchangedPermissions)
        ->and($auditLog->after_values['permissions'])->toBe(collect($changedPermissions)->sort()->values()->all());
});

test('attempting to change the system_admin role is rejected', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = userWithPermissions([PermissionName::SystemManageSettings->value]);

    $systemAdminRole = Role::findByName(RoleName::SystemAdmin->value, 'web');
    $currentPermissions = $systemAdminRole->permissions->pluck('name')->sort()->values()->all();
    $tamperedPermissions = array_slice($currentPermissions, 0, -1);

    $response = $this->actingAs($admin)->put(route('permission-matrix.update'), [
        'matrix' => [
            RoleName::SystemAdmin->value => $tamperedPermissions,
        ],
    ]);

    $response->assertSessionHasErrors('matrix.'.RoleName::SystemAdmin->value);

    expect($systemAdminRole->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe($currentPermissions);
    expect(AuditLog::where('action', AuditAction::RolePermissionsUpdated->value)->exists())->toBeFalse();
});

test('a user cannot strip system.manage_settings from a role they currently hold', function () {
    $this->seed(RolePermissionSeeder::class);

    $directorRole = Role::findByName(RoleName::Director->value, 'web');
    $directorRole->givePermissionTo(PermissionName::SystemManageSettings->value);
    $currentPermissions = $directorRole->fresh()->permissions->pluck('name')->sort()->values()->all();

    $actor = User::factory()->create();
    $actor->assignRole(RoleName::Director->value);

    $withoutManageSettings = array_values(array_diff($currentPermissions, [PermissionName::SystemManageSettings->value]));

    $response = $this->actingAs($actor)->put(route('permission-matrix.update'), [
        'matrix' => [
            RoleName::Director->value => $withoutManageSettings,
        ],
    ]);

    $response->assertSessionHasErrors('matrix.'.RoleName::Director->value);

    expect($directorRole->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe($currentPermissions);
    expect(AuditLog::where('action', AuditAction::RolePermissionsUpdated->value)->exists())->toBeFalse();
});

test('an invalid permission name is rejected', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = userWithPermissions([PermissionName::SystemManageSettings->value]);

    $response = $this->actingAs($admin)->put(route('permission-matrix.update'), [
        'matrix' => [
            RoleName::Employee->value => ['not.a_real_permission'],
        ],
    ]);

    $response->assertSessionHasErrors('matrix.'.RoleName::Employee->value.'.0');
    expect(AuditLog::where('action', AuditAction::RolePermissionsUpdated->value)->exists())->toBeFalse();
});

test('an unknown role key is rejected', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = userWithPermissions([PermissionName::SystemManageSettings->value]);

    $response = $this->actingAs($admin)->put(route('permission-matrix.update'), [
        'matrix' => [
            'not_a_real_role' => [PermissionName::TaskView->value],
        ],
    ]);

    $response->assertSessionHasErrors('matrix');
});

test('saved permission changes take effect immediately because the permission cache is cleared', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = userWithPermissions([PermissionName::SystemManageSettings->value]);

    $employeeRole = Role::findByName(RoleName::Employee->value, 'web');
    $currentPermissions = $employeeRole->permissions->pluck('name')->sort()->values()->all();
    $newPermissions = [...$currentPermissions, PermissionName::TaskExport->value];

    // Warm the Spatie permission cache before the update, as a real request would.
    app(PermissionRegistrar::class)->getPermissions();

    $this->actingAs($admin)->put(route('permission-matrix.update'), [
        'matrix' => [
            RoleName::Employee->value => $newPermissions,
        ],
    ])->assertRedirect(route('permission-matrix.index'));

    $response = $this->actingAs($admin)->get(route('permission-matrix.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('matrix.'.RoleName::Employee->value, fn ($permissions) => collect($permissions)
                ->contains(PermissionName::TaskExport->value)));
});
