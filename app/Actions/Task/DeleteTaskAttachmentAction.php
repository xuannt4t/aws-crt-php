<?php

namespace App\Actions\Task;

use App\Enums\AuditAction;
use App\Enums\TaskActivityType;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final readonly class DeleteTaskAttachmentAction
{
    public function __construct(
        private AuditLogger $auditLogger,
        private RecordTaskActivityAction $recordActivity,
    ) {}

    public function execute(User $actor, Task $task, TaskAttachment $attachment): void
    {
        // Giữ file trên disk: bản ghi xoá mềm nên vẫn khôi phục được khi xoá nhầm.
        DB::transaction(function () use ($actor, $task, $attachment): void {
            $this->auditLogger->record(
                actor: $actor,
                action: AuditAction::TaskAttachmentDeleted,
                subject: $attachment,
                metadata: [
                    'task_id' => $attachment->task_id,
                    'original_name' => $attachment->original_name,
                    'size_bytes' => $attachment->size_bytes,
                ],
            );

            $this->recordActivity->execute($actor, $task, TaskActivityType::AttachmentRemoved, [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
            ]);

            $attachment->delete();
        });
    }
}
