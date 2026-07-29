<?php

namespace App\Actions\Task;

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateTaskProgressAction
{
    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    public function execute(User $actor, Task $task, int $progress): Task
    {
        return DB::transaction(function () use ($actor, $task, $progress): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

            if ($lockedTask->status !== TaskStatus::InProgress) {
                throw ValidationException::withMessages([
                    'progress' => 'Chỉ có thể cập nhật tiến độ khi công việc đang được thực hiện.',
                ]);
            }

            $previousProgress = $lockedTask->progress;

            if ($previousProgress === $progress) {
                return $lockedTask;
            }

            $lockedTask->update(['progress' => $progress]);

            $this->recordActivity->execute($actor, $lockedTask, TaskActivityType::ProgressUpdated, [
                'from' => $previousProgress,
                'to' => $progress,
            ]);

            return $lockedTask->refresh();
        });
    }
}
