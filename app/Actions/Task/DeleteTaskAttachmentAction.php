<?php

namespace App\Actions\Task;

use App\Enums\AuditAction;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

final readonly class DeleteTaskAttachmentAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function execute(User $actor, TaskAttachment $attachment): void
    {
        // Giữ file trên disk: bản ghi xoá mềm nên vẫn khôi phục được khi xoá nhầm.
        DB::transaction(function () use ($actor, $attachment): void {
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

            $attachment->delete();
        });
    }
}
