<?php

namespace App\Actions\Task;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;

final class CreateTaskCommentAction
{
    public function execute(User $actor, Task $task, string $body): TaskComment
    {
        return $task->comments()->create([
            'author_id' => $actor->id,
            'body' => $body,
        ]);
    }
}
