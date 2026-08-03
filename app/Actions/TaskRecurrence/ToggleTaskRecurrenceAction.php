<?php

namespace App\Actions\TaskRecurrence;

use App\Models\TaskRecurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ToggleTaskRecurrenceAction
{
    /**
     * Lật is_active. Không đụng last_generated_for — bật lại một mẫu không
     * sinh bù các kỳ đã bỏ lỡ trong lúc tắt (spec mục 4.3).
     */
    public function execute(User $actor, TaskRecurrence $taskRecurrence): TaskRecurrence
    {
        return DB::transaction(function () use ($taskRecurrence): TaskRecurrence {
            $taskRecurrence->update(['is_active' => ! $taskRecurrence->is_active]);

            return $taskRecurrence->refresh();
        });
    }
}
