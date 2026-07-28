<?php

namespace App\Actions\Task;

use App\Enums\AuditAction;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class DeleteTaskAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(User $actor, Task $task): void
    {
        DB::transaction(function () use ($actor, $task): void {
            $beforeValues = Arr::only($task->toArray(), [
                'organization_unit_id',
                'parent_id',
                'creator_id',
                'assignee_id',
                'title',
                'status',
                'priority',
                'due_at',
            ]);

            $task->delete();

            $this->auditLogger->record(
                actor: $actor,
                action: AuditAction::TaskDeleted,
                subject: $task,
                beforeValues: $beforeValues,
                afterValues: ['deleted_at' => $task->deleted_at?->toISOString()],
            );
        });
    }
}
