<?php

use App\Actions\Task\CreateTaskCommentAction;
use App\Actions\Task\TransitionTaskStatusAction;
use App\Actions\Task\UpdateTaskAction;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskNotification;
use Illuminate\Support\Facades\Notification;

test('assigning a task notifies only the new assignee', function () {
    Notification::fake();

    $creator = User::factory()->create();
    $previousAssignee = User::factory()->create();
    $newAssignee = User::factory()->create();
    $task = Task::factory()->create([
        'creator_id' => $creator->id,
        'assignee_id' => $previousAssignee->id,
    ]);

    app(UpdateTaskAction::class)->execute($creator, $task, [
        'assignee_id' => $newAssignee->id,
    ]);

    Notification::assertSentTo($newAssignee, TaskNotification::class, function (TaskNotification $notification) use ($newAssignee): bool {
        return $notification->toArray($newAssignee)['event'] === 'assigned';
    });
    Notification::assertNothingSentTo($creator);
    Notification::assertNothingSentTo($previousAssignee);
});

test('a status change notifies task participants except the actor', function () {
    Notification::fake();

    $creator = User::factory()->create();
    $assignee = User::factory()->create();
    $task = Task::factory()->create([
        'creator_id' => $creator->id,
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::Todo,
    ]);

    app(TransitionTaskStatusAction::class)->execute($assignee, $task, TaskStatus::InProgress);

    Notification::assertSentTo($creator, TaskNotification::class, function (TaskNotification $notification) use ($creator): bool {
        return $notification->toArray($creator)['event'] === 'status_changed';
    });
    Notification::assertNothingSentTo($assignee);
});

test('a comment notifies the creator and assignee without duplicates', function () {
    Notification::fake();

    $participant = User::factory()->create();
    $actor = User::factory()->create();
    $task = Task::factory()->create([
        'creator_id' => $participant->id,
        'assignee_id' => $participant->id,
    ]);

    app(CreateTaskCommentAction::class)->execute($actor, $task, 'Đã bổ sung nội dung báo cáo.');

    Notification::assertSentToTimes($participant, TaskNotification::class, 1);
    Notification::assertSentTo($participant, TaskNotification::class, function (TaskNotification $notification) use ($participant): bool {
        return $notification->toArray($participant)['event'] === 'commented';
    });
    Notification::assertNothingSentTo($actor);
});
