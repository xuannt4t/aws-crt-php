<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @return array<string, list<PermissionName>>
     */
    private function baseline(): array
    {
        return [
            RoleName::SystemAdmin->value => [PermissionName::TaskViewAll, PermissionName::ProjectViewAll],
            RoleName::Director->value => [PermissionName::TaskViewAll, PermissionName::ProjectViewAll],
            RoleName::DepartmentManager->value => [PermissionName::TaskViewDepartment, PermissionName::ProjectViewDepartment],
            RoleName::ProjectManager->value => [PermissionName::TaskViewOwn, PermissionName::ProjectViewOwn],
            RoleName::Employee->value => [PermissionName::TaskViewOwn, PermissionName::ProjectViewOwn],
            RoleName::Auditor->value => [PermissionName::TaskViewAll, PermissionName::ProjectViewAll],
        ];
    }

    /**
     * @return list<string>
     */
    private function scopePermissionValues(): array
    {
        return [
            PermissionName::TaskViewOwn->value,
            PermissionName::TaskViewDepartment->value,
            PermissionName::TaskViewAll->value,
            PermissionName::ProjectViewOwn->value,
            PermissionName::ProjectViewDepartment->value,
            PermissionName::ProjectViewAll->value,
        ];
    }

    public function up(): void
    {
        foreach ($this->baseline() as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if ($role === null) {
                continue;
            }

            foreach ($permissions as $permission) {
                $role->givePermissionTo(Permission::findOrCreate($permission->value, 'web'));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $scopePermissions = $this->scopePermissionValues();

        Role::where('guard_name', 'web')->get()->each(function (Role $role) use ($scopePermissions): void {
            $role->revokePermissionTo(array_values(array_intersect(
                $role->permissions->pluck('name')->all(),
                $scopePermissions,
            )));
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
