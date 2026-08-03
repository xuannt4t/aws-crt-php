<?php

namespace App\Actions\TaskRecurrence;

use App\Models\TaskRecurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteTaskRecurrenceAction
{
    /**
     * Xoá mềm mẫu; công việc đã sinh giữ nguyên (task_recurrence_id vẫn trỏ
     * tới bản ghi đã xoá mềm này).
     */
    public function execute(User $actor, TaskRecurrence $taskRecurrence): void
    {
        DB::transaction(function () use ($taskRecurrence): void {
            $taskRecurrence->delete();
        });
    }
}
