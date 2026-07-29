<?php

namespace App\Actions\Task;

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateTaskActualQuantityAction
{
    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    public function execute(User $actor, Task $task, int $quantity): Task
    {
        return DB::transaction(function () use ($actor, $task, $quantity): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);

            if (! $lockedTask->tracksQuantity()) {
                throw ValidationException::withMessages([
                    'actual_quantity' => 'Công việc này không theo dõi bằng số lượng.',
                ]);
            }

            if ($lockedTask->status !== TaskStatus::InProgress) {
                throw ValidationException::withMessages([
                    'actual_quantity' => 'Chỉ có thể cập nhật số lượng khi công việc đang được thực hiện.',
                ]);
            }

            $previousQuantity = (int) $lockedTask->actual_quantity;

            if ($previousQuantity === $quantity) {
                return $lockedTask;
            }

            $lockedTask->update([
                'actual_quantity' => $quantity,
                'progress' => Task::progressFromQuantity((int) $lockedTask->planned_quantity, $quantity),
            ]);

            $this->recordActivity->execute($actor, $lockedTask, TaskActivityType::QuantityUpdated, [
                'from' => $previousQuantity,
                'to' => $quantity,
                'planned' => (int) $lockedTask->planned_quantity,
                'unit' => $lockedTask->quantity_unit,
            ]);

            return $lockedTask->refresh();
        });
    }
}
