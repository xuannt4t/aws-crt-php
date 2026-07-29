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
            $previousAssigneeId = $task->assignee_id;

            $task->update($data);

            if (array_key_exists('assignee_id', $data)) {
                $newAssigneeId = $data['assignee_id'] === null ? null : (int) $data['assignee_id'];

                if ($newAssigneeId !== $previousAssigneeId) {
                    $this->recordActivity->execute($actor, $task, TaskActivityType::Assigned, [
                        'from_assignee_id' => $previousAssigneeId,
                        'from_assignee_name' => $this->userName($previousAssigneeId),
                        'to_assignee_id' => $newAssigneeId,
                        'to_assignee_name' => $this->userName($newAssigneeId),
                    ]);
                }
            }

            return $task->refresh();
        });
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
