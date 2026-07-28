<?php

namespace App\Policies;

use App\Models\OrganizationUnit;
use App\Models\User;

class OrganizationUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OrganizationUnit $organizationUnit): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_system_admin;
    }

    public function update(User $user, OrganizationUnit $organizationUnit): bool
    {
        return $user->is_system_admin;
    }

    public function delete(User $user, OrganizationUnit $organizationUnit): bool
    {
        return $user->is_system_admin;
    }
}
