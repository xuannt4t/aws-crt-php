<?php

namespace App\Actions\TaskRecurrence;

use App\Models\TaskRecurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateTaskRecurrenceAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, TaskRecurrence $taskRecurrence, array $data): TaskRecurrence
    {
        return DB::transaction(function () use ($taskRecurrence, $data): TaskRecurrence {
            $taskRecurrence->update($data);

            return $taskRecurrence->refresh();
        });
    }
}
