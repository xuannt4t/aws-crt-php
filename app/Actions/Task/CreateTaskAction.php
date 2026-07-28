<?php

namespace App\Actions\Task;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;

final class CreateTaskAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): Task
    {
        return Task::create([
            ...$data,
            'creator_id' => $actor->id,
            'status' => TaskStatus::Draft,
            'progress' => 0,
        ]);
    }
}
