<?php

namespace App\Actions\Project;

use App\Enums\AuditAction;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RemoveProjectMemberAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(User $actor, Project $project, ProjectMember $member): void
    {
        if ($member->user_id === $project->owner_id) {
            throw ValidationException::withMessages([
                'user_id' => 'Không thể xoá chủ dự án khỏi danh sách thành viên.',
            ]);
        }

        DB::transaction(function () use ($actor, $project, $member): void {
            $memberUserId = $member->user_id;
            $role = $member->role->value;

            $member->delete();

            $this->auditLogger->record(
                actor: $actor,
                action: AuditAction::ProjectMemberRemoved,
                subject: $project,
                metadata: [
                    'member_user_id' => $memberUserId,
                    'role' => $role,
                ],
            );
        });
    }
}
