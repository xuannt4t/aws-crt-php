<?php

namespace App\Actions\Project;

use App\Enums\AuditAction;
use App\Enums\ProjectMemberRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final class AddProjectMemberAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{user_id: int, role: string}  $data
     */
    public function execute(User $actor, Project $project, array $data): ProjectMember
    {
        return DB::transaction(function () use ($actor, $project, $data): ProjectMember {
            $member = ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $data['user_id'],
                'role' => ProjectMemberRole::from($data['role']),
                'joined_at' => now(),
            ]);

            $this->auditLogger->record(
                actor: $actor,
                action: AuditAction::ProjectMemberAdded,
                subject: $project,
                metadata: [
                    'member_user_id' => $member->user_id,
                    'role' => $member->role->value,
                ],
            );

            return $member;
        });
    }
}
