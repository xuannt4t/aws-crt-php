<?php

namespace App\Actions\Project;

use App\Enums\AuditAction;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class DeleteProjectAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(User $actor, Project $project): void
    {
        DB::transaction(function () use ($actor, $project): void {
            $beforeValues = Arr::only($project->toArray(), [
                'organization_unit_id',
                'owner_id',
                'code',
                'name',
                'status',
            ]);

            $taskIds = $project->tasks()->pluck('id');

            $project->tasks()->delete();

            $project->delete();

            $this->auditLogger->record(
                actor: $actor,
                action: AuditAction::ProjectDeleted,
                subject: $project,
                beforeValues: $beforeValues,
                afterValues: ['deleted_at' => $project->deleted_at?->toISOString()],
                metadata: [
                    'task_count' => $taskIds->count(),
                    'task_ids' => $taskIds->all(),
                ],
            );
        });
    }
}
