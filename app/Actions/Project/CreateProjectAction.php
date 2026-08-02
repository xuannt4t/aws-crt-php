<?php

namespace App\Actions\Project;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateProjectAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): Project
    {
        return DB::transaction(function () use ($data): Project {
            $project = Project::create([
                ...$data,
                'status' => $data['status'] ?? ProjectStatus::Planning,
            ]);

            ProjectMember::create([
                'project_id' => $project->id,
                'user_id' => $project->owner_id,
                'role' => ProjectMemberRole::Manager,
                'joined_at' => now(),
            ]);

            return $project;
        });
    }
}
