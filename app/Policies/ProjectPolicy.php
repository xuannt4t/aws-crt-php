<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Project;
use App\Models\User;

final class ProjectPolicy
{
    /**
     * Cổng module theo mô hình hai lớp của spec: project.view mở màn hình danh
     * sách, còn project.view_* quyết định thấy được bản ghi nào. Nội dung danh
     * sách vẫn được giới hạn bằng Project::scopeVisibleTo(), nên viewAny và
     * view() luôn đồng nhất — người thấy việc dự án trong danh sách cũng mở được nó.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ProjectView->value);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->can(PermissionName::ProjectView->value)
            && $this->isVisible($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::ProjectCreate->value);
    }

    public function update(User $user, Project $project): bool
    {
        return ($user->can(PermissionName::ProjectUpdate->value) && $this->isVisible($user, $project))
            || $project->isManager($user);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can(PermissionName::ProjectDelete->value)
            && $this->isVisible($user, $project);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return ($user->can(PermissionName::ProjectManageMembers->value) && $this->isVisible($user, $project))
            || $project->isManager($user);
    }

    public function close(User $user, Project $project): bool
    {
        return ($user->can(PermissionName::ProjectClose->value) && $this->isVisible($user, $project))
            || $project->isManager($user);
    }

    /**
     * Hỏi lại Project::scopeVisibleTo() — định nghĩa DUY NHẤT của phạm vi dữ
     * liệu. Nhánh "hoặc là quản lý việc dự án" vẫn giữ nguyên hiệu lực: quản lý dự
     * án luôn là thành viên nên đã nằm sẵn trong phạm vi own.
     */
    private function isVisible(User $user, Project $project): bool
    {
        return Project::query()->whereKey($project->getKey())->visibleTo($user)->exists();
    }
}
