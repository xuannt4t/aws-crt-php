<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Project;
use App\Models\User;

final class ProjectPolicy
{
    /**
     * Mọi người dùng đã đăng nhập đều mở được danh sách dự án; nội dung danh
     * sách được giới hạn theo tầm nhìn bằng Project::scopeVisibleTo() — người
     * không có quyền project.view chỉ thấy dự án mình là thành viên.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $project->isVisibleTo($user);
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
