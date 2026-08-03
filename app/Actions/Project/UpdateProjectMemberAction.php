<?php

namespace App\Actions\Project;

use App\Enums\AuditAction;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectTaskVisibility;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Đổi mỗi lần cả vai trò lẫn quyền xem việc của thành viên — hai giá trị này
 * ràng buộc lẫn nhau (nâng lên/hạ khỏi quản lý dự án) nên xử lý cùng một hành
 * động thay vì tách riêng.
 */
final class UpdateProjectMemberAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(
        User $actor,
        Project $project,
        ProjectMember $member,
        string $role,
        string $taskVisibility,
    ): ProjectMember {
        return DB::transaction(function () use ($actor, $project, $member, $role, $taskVisibility): ProjectMember {
            $oldRole = $member->role;
            $oldTaskVisibility = $member->task_visibility;
            $newRole = ProjectMemberRole::from($role);
            $requestedTaskVisibility = ProjectTaskVisibility::from($taskVisibility);

            // Nâng lên quản lý dự án thì luôn chuyển cột thành "all"; hạ khỏi
            // quản lý dự án thì giữ nguyên giá trị đang lưu, bỏ qua giá trị
            // vừa gửi lên. Các trường hợp còn lại dùng đúng giá trị yêu cầu.
            $newTaskVisibility = match (true) {
                $newRole === ProjectMemberRole::Manager => ProjectTaskVisibility::All,
                $oldRole === ProjectMemberRole::Manager => $oldTaskVisibility,
                default => $requestedTaskVisibility,
            };

            $member->update([
                'role' => $newRole,
                'task_visibility' => $newTaskVisibility,
            ]);

            $this->auditLogger->record(
                actor: $actor,
                action: AuditAction::ProjectMemberRoleUpdated,
                subject: $project,
                beforeValues: ['role' => $oldRole->value],
                afterValues: ['role' => $newRole->value],
                metadata: [
                    'member_user_id' => $member->user_id,
                    'role' => $newRole->value,
                    'task_visibility' => $newTaskVisibility->value,
                ],
            );

            return $member->refresh();
        });
    }
}
