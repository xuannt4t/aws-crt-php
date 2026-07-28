<?php

namespace App\Actions\Task;

use App\Models\Task;
use App\Support\TaskDescriptionSanitizer;
use Illuminate\Validation\ValidationException;

final class UpdateTaskAction
{
    public function __construct(
        private readonly TaskDescriptionSanitizer $descriptionSanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Task $task, array $data): Task
    {
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $this->guardAgainstCircularReference($task, (int) $data['parent_id']);
        }

        $data['description'] = $this->descriptionSanitizer->sanitize($data['description'] ?? null);

        $task->update($data);

        return $task->refresh();
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
