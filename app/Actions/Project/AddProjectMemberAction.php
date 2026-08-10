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

final class AddProjectMemberAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{user_id: int, role: string, task_visibility?: string|null}  $data
     */
    public function execute(User $actor, Project $project, array $data): ProjectMember
    {
        return DB::transaction(function () use ($actor, $project, $data): ProjectMember {
            $role = ProjectMemberRole::from($data['role']);

            // Quản lý việc dự án luôn hiệu lực toàn bộ việc dự án — cột lưu trực
            // tiếp "all" khi thêm với vai trò này, dù người dùng gửi gì khác.
            $taskVisibility = $role === ProjectMemberRole::Manager
                ? ProjectTaskVisibility::All
                : (isset($data['task_visibility'])
                    ? ProjectTaskVisibility::from($data['task_visibility'])
                    : ProjectTaskVisibility::Own);

            $member = ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $data['user_id'],
                'role' => $role,
                'task_visibility' => $taskVisibility,
                'joined_at' => now(),
            ]);

            $this->auditLogger->record(
                actor: $actor,
                action: AuditAction::ProjectMemberAdded,
                subject: $project,
                metadata: [
                    'member_user_id' => $member->user_id,
                    'role' => $member->role->value,
                    'task_visibility' => $member->task_visibility->value,
                ],
            );

            return $member;
        });
    }
}
