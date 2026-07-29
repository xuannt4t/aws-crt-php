<?php

namespace App\Actions\Task;

use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskDescriptionSanitizer;
use Illuminate\Support\Facades\DB;

final class CreateTaskAction
{
    public function __construct(
        private readonly TaskDescriptionSanitizer $descriptionSanitizer,
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): Task
    {
        $data['description'] = $this->descriptionSanitizer->sanitize($data['description'] ?? null);

        return DB::transaction(function () use ($actor, $data): Task {
            $plannedQuantity = $data['planned_quantity'] ?? null;

            $task = Task::create([
                ...$data,
                'creator_id' => $actor->id,
                'status' => TaskStatus::Draft,
                'progress' => 0,
                'planned_quantity' => $plannedQuantity,
                'actual_quantity' => $plannedQuantity === null ? null : 0,
                'quantity_unit' => $plannedQuantity === null ? null : ($data['quantity_unit'] ?? null),
            ]);

            $this->recordActivity->execute($actor, $task, TaskActivityType::Created);

            return $task;
        });
    }
}
