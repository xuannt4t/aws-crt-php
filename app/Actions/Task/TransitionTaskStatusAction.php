<?php

namespace App\Actions\Task;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransitionTaskStatusAction
{
    public function execute(
        User $actor,
        Task $task,
        TaskStatus $targetStatus,
        ?TaskStatus $expectedStatus = null,
    ): Task {
        return DB::transaction(function () use ($actor, $task, $targetStatus, $expectedStatus): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
            $this->validateTransition($lockedTask, $targetStatus, $expectedStatus);
            $fromStatus = $lockedTask->status;

            $lockedTask->update(['status' => $targetStatus]);

            TaskStatusHistory::create([
                'task_id' => $lockedTask->id,
                'actor_id' => $actor->id,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
            ]);

            return $lockedTask->refresh();
        });
    }

    private function validateTransition(
        Task $task,
        TaskStatus $targetStatus,
        ?TaskStatus $expectedStatus,
    ): void {
        $allowedTransitions = [
            TaskStatus::Draft->value => [TaskStatus::Todo],
            TaskStatus::Todo->value => [TaskStatus::InProgress],
            TaskStatus::InProgress->value => [TaskStatus::WaitingReview],
            TaskStatus::WaitingReview->value => [TaskStatus::InProgress],
        ];

        if (($expectedStatus !== null && $task->status !== $expectedStatus)
            || ! in_array($targetStatus, $allowedTransitions[$task->status->value] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Không thể chuyển trạng thái từ {$task->status->value} sang {$targetStatus->value}.",
            ]);
        }

        if ($targetStatus === TaskStatus::Todo && $task->assignee_id === null) {
            throw ValidationException::withMessages([
                'assignee_id' => 'Công việc phải có người phụ trách trước khi được giao.',
            ]);
        }
    }
}
