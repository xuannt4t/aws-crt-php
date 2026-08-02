<?php

namespace App\Listeners;

use App\Enums\TaskActivityType;
use App\Events\TaskActivityRecorded;
use App\Models\TaskActivity;
use App\Models\User;
use App\Notifications\TaskNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

final class SendTaskActivityNotifications
{
    public function handle(TaskActivityRecorded $event): void
    {
        $activity = TaskActivity::query()
            ->with(['task.creator', 'task.assignee', 'actor'])
            ->find($event->activityId);

        if ($activity === null || $activity->task === null) {
            return;
        }

        $copy = $this->copy($activity);

        if ($copy === null) {
            return;
        }

        $recipients = $this->recipients($activity);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new TaskNotification(
            event: $copy['event'],
            task: $activity->task,
            title: $copy['title'],
            message: $copy['message'],
            actor: $activity->actor,
        ));
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(TaskActivity $activity): Collection
    {
        $task = $activity->task;

        $users = match ($activity->type) {
            TaskActivityType::Created, TaskActivityType::Assigned => collect([$task->assignee]),
            TaskActivityType::StatusChanged,
            TaskActivityType::ProgressUpdated,
            TaskActivityType::QuantityUpdated,
            TaskActivityType::Commented,
            TaskActivityType::AttachmentAdded => collect([$task->creator, $task->assignee]),
            default => collect(),
        };

        return $users
            ->filter(fn (?User $user): bool => $user !== null
                && $user->deleted_at === null
                && $user->is_active
                && $user->id !== $activity->actor_id)
            ->unique('id')
            ->values();
    }

    /**
     * @return array{event: string, title: string, message: string}|null
     */
    private function copy(TaskActivity $activity): ?array
    {
        $taskTitle = $activity->task->title;
        $actorName = $activity->actor?->name ?? 'Hệ thống';

        return match ($activity->type) {
            TaskActivityType::Created, TaskActivityType::Assigned => [
                'event' => 'assigned',
                'title' => 'Bạn được giao công việc mới',
                'message' => "{$actorName} đã giao “{$taskTitle}” cho bạn.",
            ],
            TaskActivityType::StatusChanged => [
                'event' => 'status_changed',
                'title' => 'Trạng thái công việc đã thay đổi',
                'message' => "{$actorName} đã cập nhật trạng thái “{$taskTitle}”.",
            ],
            TaskActivityType::ProgressUpdated, TaskActivityType::QuantityUpdated => [
                'event' => 'progress_updated',
                'title' => 'Tiến độ công việc đã cập nhật',
                'message' => "{$actorName} đã cập nhật tiến độ “{$taskTitle}”.",
            ],
            TaskActivityType::Commented => [
                'event' => 'commented',
                'title' => 'Có trao đổi mới',
                'message' => "{$actorName} đã bình luận trong “{$taskTitle}”.",
            ],
            TaskActivityType::AttachmentAdded => [
                'event' => 'attachment_added',
                'title' => 'Có tệp đính kèm mới',
                'message' => "{$actorName} đã thêm tệp vào “{$taskTitle}”.",
            ],
            default => null,
        };
    }
}
