<?php

namespace App\Actions\Task;

use App\Enums\TaskActivityType;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskDescriptionSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateTaskAction
{
    public function __construct(
        private readonly TaskDescriptionSanitizer $descriptionSanitizer,
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Task $task, array $data): Task
    {
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $this->guardAgainstCircularReference($task, (int) $data['parent_id']);
        }

        $data['description'] = $this->descriptionSanitizer->sanitize($data['description'] ?? null);

        return DB::transaction(function () use ($actor, $task, $data): Task {
            $lockedTask = Task::query()->lockForUpdate()->findOrFail($task->id);
            $previousAssigneeId = $lockedTask->assignee_id;

            $lockedTask->update($this->withNormalizedQuantity($lockedTask, $data));

            if (array_key_exists('assignee_id', $data)) {
                $newAssigneeId = $data['assignee_id'] === null ? null : (int) $data['assignee_id'];

                if ($newAssigneeId !== $previousAssigneeId) {
                    $this->recordActivity->execute($actor, $lockedTask, TaskActivityType::Assigned, [
                        'from_assignee_id' => $previousAssigneeId,
                        'from_assignee_name' => $this->userName($previousAssigneeId),
                        'to_assignee_id' => $newAssigneeId,
                        'to_assignee_name' => $this->userName($newAssigneeId),
                    ]);
                }
            }

            return $lockedTask->refresh();
        });
    }

    /**
     * Giữ ba cột số lượng luôn nhất quán: cùng có giá trị hoặc cùng rỗng,
     * và progress luôn khớp với số lượng hiện tại.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withNormalizedQuantity(Task $task, array $data): array
    {
        if (! array_key_exists('planned_quantity', $data)) {
            return $data;
        }

        $planned = $data['planned_quantity'] === null ? null : (int) $data['planned_quantity'];

        if ($planned === null) {
            // Tắt chế độ đo sản lượng: giữ nguyên progress đã báo cáo gần nhất.
            $data['planned_quantity'] = null;
            $data['actual_quantity'] = null;
            $data['quantity_unit'] = null;

            return $data;
        }

        $actual = $task->tracksQuantity() ? (int) $task->actual_quantity : 0;

        $data['planned_quantity'] = $planned;
        $data['actual_quantity'] = $actual;
        $data['quantity_unit'] = $data['quantity_unit'] ?? null;
        $data['progress'] = Task::progressFromQuantity($planned, $actual);

        return $data;
    }

    private function userName(?int $userId): ?string
    {
        if ($userId === null) {
            return null;
        }

        return User::withTrashed()->whereKey($userId)->value('name');
    }

    private function guardAgainstCircularReference(Task $task, int $parentId): void
    {
        $ancestor = Task::find($parentId);

        while ($ancestor !== null) {
            if ($ancestor->is($task)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Không thể chọn một công việc con làm công việc cha.',
                ]);
            }

            $ancestor = $ancestor->parent;
        }
    }
}
