<?php

namespace App\Actions\Project;

use App\Enums\AuditAction;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CloseProjectAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(User $actor, Project $project, ?string $reason): Project
    {
        if ($project->isClosed()) {
            // Gắn vào close_reason vì đó là trường duy nhất hộp thoại "Đóng dự
            // án" trên Projects/Show.vue hiển thị lỗi.
            throw ValidationException::withMessages([
                'close_reason' => 'Dự án đã được đóng.',
            ]);
        }

        return DB::transaction(function () use ($actor, $project, $reason): Project {
            $openTaskIds = $project->openTasks()->pluck('id');
            $openTaskCount = $openTaskIds->count();

            if ($openTaskCount > 0) {
                $project->update([
                    'status' => ProjectStatus::Completed,
                    'closed_at' => now(),
                    'close_reason' => $reason,
                ]);

                $this->auditLogger->record(
                    actor: $actor,
                    action: AuditAction::ProjectClosedWithException,
                    subject: $project,
                    metadata: [
                        'open_task_count' => $openTaskCount,
                        'open_task_ids' => $openTaskIds->take(20)->values()->all(),
                    ],
                );

                return $project;
            }

            $project->update([
                'status' => ProjectStatus::Completed,
                'closed_at' => now(),
                'close_reason' => null,
            ]);

            $this->auditLogger->record(
                actor: $actor,
                action: AuditAction::ProjectClosed,
                subject: $project,
            );

            return $project;
        });
    }
}
