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
        return $user->can(PermissionName::TaskView->value)
            && $this->isVisible($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::TaskCreate->value);
    }

    public function update(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskUpdate->value)
            && $this->isVisible($user, $task);
    }

    public function updateProgress(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskUpdate->value)
            && $task->assignee_id === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskDelete->value)
            && $this->isVisible($user, $task);
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskAssign->value)
            && $this->isVisible($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskView->value)
            && $user->can(PermissionName::TaskComment->value)
            && $this->isVisible($user, $task);
    }

    public function attach(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskView->value)
            && $user->can(PermissionName::TaskComment->value)
            && $this->isVisible($user, $task);
    }

    public function downloadAttachment(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskView->value)
            && $this->isVisible($user, $task);
    }

    public function dispatch(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskAssign->value)
            && $this->isVisible($user, $task);
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

    public function recall(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskSubmit->value)
            && $task->assignee_id === $user->id;
    }

    /**
     * Hỏi lại Task::scopeVisibleTo() — định nghĩa DUY NHẤT của phạm vi dữ liệu.
     * Mọi ability chạm tới một bản ghi cụ thể (đọc hoặc ghi) phải đi qua đây,
     * không viết lại điều kiện phạm vi.
     */
    private function isVisible(User $user, Task $task): bool
    {
        return Task::query()->whereKey($task->getKey())->visibleTo($user)->exists();
    }
}
