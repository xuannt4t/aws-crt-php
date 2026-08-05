<?php

namespace App\Actions\Task;

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransitionTaskStatusAction
{
    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    public function execute(
        User $actor,
        Task $task,
        TaskStatus $targetStatus,
        ?TaskStatus $expectedStatus = null,
        ?string $reason = null,
    ): Task {
        return DB::transaction(function () use ($actor, $task, $targetStatus, $expectedStatus, $reason): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
            $this->validateTransition($lockedTask, $targetStatus, $expectedStatus);
            $fromStatus = $lockedTask->status;

            $changes = ['status' => $targetStatus];

            // Hoàn thành là điểm cuối, nên mốc thời gian và tiến độ phải khớp với
            // trạng thái. Nếu để nguyên tiến độ cũ thì danh sách sẽ hiện việc đã
            // xong mà tiến độ 40%, và tiến độ trung bình của dự án cũng sai theo.
            if ($targetStatus === TaskStatus::Completed) {
                $changes['completed_at'] = now();
                $changes['progress'] = 100;
            }

            $lockedTask->update($changes);

            TaskStatusHistory::create([
                'task_id' => $lockedTask->id,
                'actor_id' => $actor->id,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'reason' => $reason,
            ]);

            // Chỉ đính lý do khi thực sự có. Nhét `reason => null` vào mọi lần
            // chuyển trạng thái sẽ làm mọi bản ghi hoạt động cũ mang thêm một
            // khoá rỗng, và giao diện dòng thời gian phải đi kiểm tra null.
            $payload = ['from' => $fromStatus->value, 'to' => $targetStatus->value];

            if ($reason !== null) {
                $payload['reason'] = $reason;
            }

            $this->recordActivity->execute($actor, $lockedTask, TaskActivityType::StatusChanged, $payload);

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
            // Chờ kiểm tra rẽ hai nhánh: người duyệt chốt hoàn thành, hoặc trả
            // lại cho người làm (cũng là đường người làm tự thu hồi yêu cầu).
            TaskStatus::WaitingReview->value => [TaskStatus::InProgress, TaskStatus::Completed],
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
