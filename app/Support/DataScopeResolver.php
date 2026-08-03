<?php

namespace App\Support;

use App\Enums\DataScope;
use App\Enums\PermissionName;
use App\Models\User;

final class DataScopeResolver
{
    public function forTasks(User $user): DataScope
    {
        return $this->widestScope(
            $user,
            PermissionName::TaskViewAll,
            PermissionName::TaskViewDepartment,
        );
    }

    public function forProjects(User $user): DataScope
    {
        return $this->widestScope(
            $user,
            PermissionName::ProjectViewAll,
            PermissionName::ProjectViewDepartment,
        );
    }

    private function widestScope(
        User $user,
        PermissionName $all,
        PermissionName $department,
    ): DataScope {
        $permissions = $user->getAllPermissions()->pluck('name');

        if ($permissions->contains($all->value)) {
            return DataScope::All;
        }

        if ($permissions->contains($department->value)) {
            return DataScope::Department;
        }

        return DataScope::Own;
    }
}
