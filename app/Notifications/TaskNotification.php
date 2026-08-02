<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

final class TaskNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private readonly string $createdAt;

    public function __construct(
        private readonly string $event,
        private readonly Task $task,
        private readonly string $title,
        private readonly string $message,
        private readonly ?User $actor = null,
        private readonly ?string $deduplicationKey = null,
        bool $afterCommit = true,
    ) {
        if ($afterCommit) {
            $this->afterCommit();
        }

        $this->createdAt = now()->toIso8601String();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'title' => $this->title,
            'message' => $this->message,
            'url' => route('tasks.show', $this->task),
            'task_id' => $this->task->id,
            'actor' => $this->actor ? [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
                'avatar_url' => $this->actor->avatar_url,
            ] : null,
            'deduplication_key' => $this->deduplicationKey,
            'created_at' => $this->createdAt,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
