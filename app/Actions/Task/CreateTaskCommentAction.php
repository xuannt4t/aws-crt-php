<?php

namespace App\Actions\Task;

use App\Enums\TaskActivityType;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateTaskCommentAction
{
    // Số ký tự cắt bằng Str::limit trước khi cộng thêm dấu '…', để tổng độ dài excerpt không vượt 120.
    private const EXCERPT_LENGTH = 119;

    public function __construct(
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    public function execute(User $actor, Task $task, string $body): TaskComment
    {
        return DB::transaction(function () use ($actor, $task, $body): TaskComment {
            $comment = $task->comments()->create([
                'author_id' => $actor->id,
                'body' => $body,
            ]);

            $this->recordActivity->execute($actor, $task, TaskActivityType::Commented, [
                'comment_id' => $comment->id,
                'excerpt' => Str::limit($body, self::EXCERPT_LENGTH, '…'),
            ]);

            return $comment;
        });
    }
}
