<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Project;
use App\Models\User;

final class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ProjectView->value);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->can(PermissionName::ProjectView->value)
            || $project->isMember($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ProjectCreate->value);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can(PermissionName::ProjectUpdate->value)
            || $project->isManager($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can(PermissionName::ProjectDelete->value);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $user->can(PermissionName::ProjectManageMembers->value)
            || $project->isManager($user);
    }

    public function close(User $user, Project $project): bool
    {
        return $user->can(PermissionName::ProjectClose->value)
            || $project->isManager($user);
    }
}
