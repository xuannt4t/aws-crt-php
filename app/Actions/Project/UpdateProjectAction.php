<?php

namespace App\Actions\Project;

use App\Enums\ProjectMemberRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateProjectAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data): Project {
            $previousOwnerId = $project->owner_id;

            $project->update($data);

            $newOwnerId = $data['owner_id'] ?? $previousOwnerId;

            if ((int) $newOwnerId !== (int) $previousOwnerId) {
                $this->ensureOwnerIsManager($project, (int) $newOwnerId);
            }

            return $project->refresh();
        });
    }

    private function ensureOwnerIsManager(Project $project, int $ownerId): void
    {
        $membership = ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $ownerId)
            ->first();

        if ($membership === null) {
            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $ownerId,
                'role' => ProjectMemberRole::Manager,
                'joined_at' => now(),
            ]);

            return;
        }

        if ($membership->role !== ProjectMemberRole::Manager) {
            $membership->update(['role' => ProjectMemberRole::Manager]);
        }
    }
}
