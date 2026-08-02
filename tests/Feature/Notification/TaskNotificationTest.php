<?php

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskNotification;
use Illuminate\Support\Facades\Notification;

test('task notifications use database and broadcast with a frontend ready payload', function () {
    Notification::fake();

    $recipient = User::factory()->create();
    $actor = User::factory()->create(['name' => 'Người giao việc']);
    $task = Task::factory()->create(['title' => 'Hoàn thành báo cáo']);

    $recipient->notify(new TaskNotification(
        event: 'assigned',
        task: $task,
        title: 'Bạn được giao công việc mới',
        message: 'Người giao việc đã giao “Hoàn thành báo cáo” cho bạn.',
        actor: $actor,
        deduplicationKey: 'task:'.$task->id.':assigned:user:'.$recipient->id,
    ));

    Notification::assertSentTo($recipient, TaskNotification::class, function (TaskNotification $notification) use ($actor, $recipient, $task): bool {
        expect($notification->via($recipient))->toBe(['database', 'broadcast'])
            ->and($notification->toArray($recipient))->toMatchArray([
                'event' => 'assigned',
                'title' => 'Bạn được giao công việc mới',
                'message' => 'Người giao việc đã giao “Hoàn thành báo cáo” cho bạn.',
                'url' => route('tasks.show', $task),
                'task_id' => $task->id,
                'actor' => [
                    'id' => $actor->id,
                    'name' => 'Người giao việc',
                    'avatar_url' => null,
                ],
                'deduplication_key' => 'task:'.$task->id.':assigned:user:'.$recipient->id,
            ])
            ->and($notification->toArray($recipient)['created_at'])->toBeString();

        return true;
    });
});
