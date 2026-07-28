<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::UserView->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(PermissionName::UserView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::UserCreate->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(PermissionName::UserUpdate->value);
    }

    public function disable(User $user, User $model): bool
    {
        return $user->can(PermissionName::UserDisable->value) && $user->isNot($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(PermissionName::UserDisable->value) && $user->isNot($model);
    }
}
