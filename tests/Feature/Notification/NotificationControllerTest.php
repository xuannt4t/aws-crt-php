<?php

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskNotification;
use Illuminate\Support\Str;

function createDatabaseTaskNotification(User $user, Task $task, string $event = 'assigned', bool $read = false): string
{
    $id = (string) Str::uuid();

    $user->notifications()->create([
        'id' => $id,
        'type' => TaskNotification::class,
        'data' => [
            'event' => $event,
            'title' => 'Thông báo '.$event,
            'message' => 'Nội dung '.$event,
            'url' => route('tasks.show', $task),
            'task_id' => $task->id,
            'actor' => null,
            'deduplication_key' => null,
            'created_at' => now()->toIso8601String(),
        ],
        'read_at' => $read ? now() : null,
    ]);

    return $id;
}

test('notification index returns only the signed in users ten newest notifications', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $task = Task::factory()->create();

    foreach (range(1, 12) as $index) {
        createDatabaseTaskNotification($user, $task, 'event_'.$index, $index === 1);
        $this->travel(1)->seconds();
    }
    createDatabaseTaskNotification($other, $task, 'private');

    $this->actingAs($user)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonCount(10, 'notifications')
        ->assertJsonPath('unread_count', 11)
        ->assertJsonPath('notifications.0.event', 'event_12')
        ->assertJsonMissing(['event' => 'private']);
});

test('a user can mark only their own notification as read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $task = Task::factory()->create();
    $ownId = createDatabaseTaskNotification($user, $task);
    $otherId = createDatabaseTaskNotification($other, $task);

    $this->actingAs($user)
        ->patchJson(route('notifications.read', $ownId))
        ->assertOk()
        ->assertJsonPath('notification.id', $ownId);

    expect($user->notifications()->findOrFail($ownId)->read_at)->not->toBeNull();

    $this->actingAs($user)
        ->patchJson(route('notifications.read', $otherId))
        ->assertNotFound();
});

test('mark all read affects only the signed in user and notification routes require authentication', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $task = Task::factory()->create();
    createDatabaseTaskNotification($user, $task);
    createDatabaseTaskNotification($user, $task);
    createDatabaseTaskNotification($other, $task);

    $this->patchJson(route('notifications.read-all'))->assertUnauthorized();

    $this->actingAs($user)
        ->patchJson(route('notifications.read-all'))
        ->assertOk()
        ->assertJsonPath('unread_count', 0);

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($other->unreadNotifications()->count())->toBe(1);
});
