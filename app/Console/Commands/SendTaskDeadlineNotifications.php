<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

final class SendTaskDeadlineNotifications extends Command
{
    protected $signature = 'tasks:notify-deadlines';

    protected $description = 'Gửi thông báo cho công việc sắp đến hạn hoặc đã quá hạn';

    public function handle(): int
    {
        Task::query()
            ->with(['creator', 'assignee'])
            ->whereNotNull('due_at')
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->where('due_at', '<=', now()->addDay())
            ->chunkById(100, function (Collection $tasks): void {
                foreach ($tasks as $task) {
                    $this->notifyTask($task);
                }
            });

        return self::SUCCESS;
    }

    private function notifyTask(Task $task): void
    {
        $event = $task->due_at->isPast() ? 'overdue' : 'due_soon';
        $title = $event === 'overdue' ? 'Công việc đã quá hạn' : 'Công việc sắp đến hạn';
        $message = $event === 'overdue'
            ? "“{$task->title}” đã quá hạn."
            : "“{$task->title}” sẽ đến hạn lúc {$task->due_at->format('H:i d/m/Y')}.";

        collect([$task->creator, $task->assignee])
            ->filter(fn (?User $user): bool => $user !== null && $user->deleted_at === null && $user->is_active)
            ->unique('id')
            ->each(function (User $recipient) use ($event, $message, $task, $title): void {
                $key = "task:{$task->id}:{$event}:user:{$recipient->id}";

                if ($recipient->notifications()->where('data->deduplication_key', $key)->exists()) {
                    return;
                }

                $recipient->notify(new TaskNotification(
                    event: $event,
                    task: $task,
                    title: $title,
                    message: $message,
                    deduplicationKey: $key,
                    afterCommit: false,
                ));
            });
    }
}
