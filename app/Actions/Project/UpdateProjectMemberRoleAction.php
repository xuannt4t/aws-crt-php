<?php

namespace App\Actions\Project;

use App\Enums\AuditAction;
use App\Enums\ProjectMemberRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class UpdateProjectMemberRoleAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(User $actor, Project $project, ProjectMember $member, string $role): ProjectMember
    {
        return DB::transaction(function () use ($actor, $project, $member, $role): ProjectMember {
            $oldRole = $member->role;
            $newRole = ProjectMemberRole::from($role);

            $member->update(['role' => $newRole]);

            $this->auditLogger->record(
                actor: $actor,
                action: AuditAction::ProjectMemberRoleUpdated,
                subject: $project,
                beforeValues: ['role' => $oldRole->value],
                afterValues: ['role' => $newRole->value],
                metadata: [
                    'member_user_id' => $member->user_id,
                    'role' => $newRole->value,
                ],
            );

            return $member->refresh();
        });
    }
}
