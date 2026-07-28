<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\OrganizationUnit;
use App\Models\User;

class OrganizationUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::OrganizationView->value);
    }

    public function view(User $user, OrganizationUnit $organizationUnit): bool
    {
        return $user->can(PermissionName::OrganizationView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::OrganizationCreate->value);
    }

    public function update(User $user, OrganizationUnit $organizationUnit): bool
    {
        return $user->can(PermissionName::OrganizationUpdate->value);
    }

    public function delete(User $user, OrganizationUnit $organizationUnit): bool
    {
        return $user->can(PermissionName::OrganizationDelete->value);
    }
}
