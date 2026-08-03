<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('migration grants baseline scope permissions to existing roles without touching other permissions', function () {
    // RefreshDatabase already ran this migration as part of migrate:fresh, before
    // any role existed. Roll it back so `up()` runs again against a role created now.
    Artisan::call('migrate:rollback', [
        '--path' => 'database/migrations/2026_08_03_120000_grant_scope_permissions_to_existing_roles.php',
        '--realpath' => false,
    ]);

    $role = Role::create(['name' => RoleName::DepartmentManager->value, 'guard_name' => 'web']);
    $role->givePermissionTo(Permission::findOrCreate(PermissionName::TaskView->value, 'web'));

    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_03_120000_grant_scope_permissions_to_existing_roles.php',
        '--realpath' => false,
    ]);

    $names = $role->fresh()->permissions->pluck('name')->all();

    expect($names)->toContain(
        PermissionName::TaskView->value,
        PermissionName::TaskViewDepartment->value,
        PermissionName::ProjectViewDepartment->value,
    );

    Artisan::call('migrate:rollback', [
        '--path' => 'database/migrations/2026_08_03_120000_grant_scope_permissions_to_existing_roles.php',
        '--realpath' => false,
    ]);

    $namesAfterRollback = $role->fresh()->permissions->pluck('name')->all();

    expect($namesAfterRollback)->toContain(PermissionName::TaskView->value)
        ->not->toContain(PermissionName::TaskViewDepartment->value, PermissionName::ProjectViewDepartment->value);
});
