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
            && $this->isVisible($user, $task)
            && ! $this->isProjectLocked($task);
    }

    public function updateProgress(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskUpdate->value)
            && $task->assignee_id === $user->id
            && ! $this->isProjectLocked($task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskDelete->value)
            && $this->isVisible($user, $task)
            && ! $this->isProjectLocked($task);
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskAssign->value)
            && $this->isVisible($user, $task)
            && ! $this->isProjectLocked($task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskView->value)
            && $user->can(PermissionName::TaskComment->value)
            && $this->isVisible($user, $task)
            && ! $this->isProjectLocked($task);
    }

    public function attach(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskView->value)
            && $user->can(PermissionName::TaskComment->value)
            && $this->isVisible($user, $task)
            && ! $this->isProjectLocked($task);
    }

    public function downloadAttachment(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskView->value)
            && $this->isVisible($user, $task);
    }

    public function dispatch(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskAssign->value)
            && $this->isVisible($user, $task)
            && ! $this->isProjectLocked($task);
    }

    public function start(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskUpdate->value)
            && $task->assignee_id === $user->id
            && ! $this->isProjectLocked($task);
    }

    public function submit(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskSubmit->value)
            && $task->assignee_id === $user->id
            && ! $this->isProjectLocked($task);
    }

    public function recall(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskSubmit->value)
            && $task->assignee_id === $user->id
            && ! $this->isProjectLocked($task);
    }

    /**
     * Duyệt và trả lại đi qua phạm vi dữ liệu chứ không gắn với người phụ trách:
     * người duyệt theo định nghĩa là người khác. Không chặn riêng trường hợp tự
     * duyệt việc của chính mình — nhân viên vốn không có quyền `task.approve`,
     * còn chặn cứng thì một việc quản lý tự giao cho mình sẽ kẹt vĩnh viễn ở
     * trạng thái chờ kiểm tra, không ai chốt được.
     */
    public function approve(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskApprove->value)
            && $this->isVisible($user, $task)
            && ! $this->isProjectLocked($task);
    }

    public function reject(User $user, Task $task): bool
    {
        return $user->can(PermissionName::TaskReject->value)
            && $this->isVisible($user, $task)
            && ! $this->isProjectLocked($task);
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

    /**
     * Hỏi lại Task::isProjectLocked() — định nghĩa DUY NHẤT của "công việc bị
     * khoá do dự án đã đóng" (dùng chung với TaskAttachmentPolicy). Mọi
     * ability ghi/tương tác (không phải xem) phải đi qua đây, không viết lại
     * điều kiện.
     */
    private function isProjectLocked(Task $task): bool
    {
        return $task->isProjectLocked();
    }
}
