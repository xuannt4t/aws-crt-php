<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\TaskRecurrence;
use App\Models\User;

final class TaskRecurrencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::TaskView->value);
    }

    public function view(User $user, TaskRecurrence $taskRecurrence): bool
    {
        return $user->can(PermissionName::TaskView->value)
            && TaskRecurrence::query()->whereKey($taskRecurrence->getKey())->visibleTo($user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::TaskCreate->value);
    }

    public function update(User $user, TaskRecurrence $taskRecurrence): bool
    {
        return $user->can(PermissionName::TaskUpdate->value)
            && ($taskRecurrence->creator_id === $user->id || $user->can(PermissionName::TaskAssign->value));
    }

    public function delete(User $user, TaskRecurrence $taskRecurrence): bool
    {
        return $user->can(PermissionName::TaskDelete->value);
    }

    public function toggle(User $user, TaskRecurrence $taskRecurrence): bool
    {
        return $this->update($user, $taskRecurrence);
    }
}
