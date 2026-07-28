<?php

namespace App\Actions\Task;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskDescriptionSanitizer;

final class CreateTaskAction
{
    public function __construct(
        private readonly TaskDescriptionSanitizer $descriptionSanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): Task
    {
        $data['description'] = $this->descriptionSanitizer->sanitize($data['description'] ?? null);

        return Task::create([
            ...$data,
            'creator_id' => $actor->id,
            'status' => TaskStatus::Draft,
            'progress' => 0,
        ]);
    }
}
