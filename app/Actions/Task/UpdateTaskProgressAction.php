<?php

namespace App\Actions\Task;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateTaskProgressAction
{
    public function execute(Task $task, int $progress): Task
    {
        return DB::transaction(function () use ($task, $progress): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

            if ($lockedTask->status !== TaskStatus::InProgress) {
                throw ValidationException::withMessages([
                    'progress' => 'Chỉ có thể cập nhật tiến độ khi công việc đang được thực hiện.',
                ]);
            }

            $lockedTask->update(['progress' => $progress]);

            return $lockedTask->refresh();
        });
    }
}
