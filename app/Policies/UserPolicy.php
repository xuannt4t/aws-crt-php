<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_system_admin;
    }

    public function update(User $user, User $model): bool
    {
        return $user->is_system_admin;
    }

    public function disable(User $user, User $model): bool
    {
        return $user->is_system_admin && $user->isNot($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->is_system_admin && $user->isNot($model);
    }
}
