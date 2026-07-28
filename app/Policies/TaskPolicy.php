<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Task;
use App\Models\User;

final class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::TaskView->value);
    }

    public function view(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::TaskCreate->value);
    }

    public function update(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskUpdate->value);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskDelete->value);
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskAssign->value);
    }

    public function dispatch(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskAssign->value);
    }

    public function start(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskUpdate->value)
            && $task->assignee_id === $user->id;
    }

    public function submit(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskSubmit->value)
            && $task->assignee_id === $user->id;
    }
}
