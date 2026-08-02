<?php

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-08-02 08:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('deadline command notifies participants about due soon and overdue tasks', function () {
    $creator = User::factory()->create();
    $assignee = User::factory()->create();

    $dueSoon = Task::factory()->create([
        'creator_id' => $creator->id,
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::InProgress,
        'due_at' => now()->addHours(12),
    ]);
    $overdue = Task::factory()->create([
        'creator_id' => $creator->id,
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::InProgress,
        'due_at' => now()->subHour(),
    ]);

    $this->artisan('tasks:notify-deadlines')->assertSuccessful();

    expect($creator->notifications()->where('data->event', 'due_soon')->where('data->task_id', $dueSoon->id)->count())->toBe(1)
        ->and($assignee->notifications()->where('data->event', 'due_soon')->where('data->task_id', $dueSoon->id)->count())->toBe(1)
        ->and($creator->notifications()->where('data->event', 'overdue')->where('data->task_id', $overdue->id)->count())->toBe(1)
        ->and($assignee->notifications()->where('data->event', 'overdue')->where('data->task_id', $overdue->id)->count())->toBe(1);
});

test('deadline command does not duplicate milestones or notify terminal tasks', function () {
    $creator = User::factory()->create();
    $assignee = User::factory()->create();
    $activeTask = Task::factory()->create([
        'creator_id' => $creator->id,
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::InProgress,
        'due_at' => now()->addHours(4),
    ]);
    Task::factory()->create([
        'creator_id' => $creator->id,
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::Completed,
        'due_at' => now()->subHour(),
    ]);
    Task::factory()->create([
        'creator_id' => $creator->id,
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::Cancelled,
        'due_at' => now()->subHour(),
    ]);

    $this->artisan('tasks:notify-deadlines')->assertSuccessful();
    $this->artisan('tasks:notify-deadlines')->assertSuccessful();

    expect($creator->notifications()->where('data->task_id', $activeTask->id)->count())->toBe(1)
        ->and($assignee->notifications()->where('data->task_id', $activeTask->id)->count())->toBe(1)
        ->and($creator->notifications()->count())->toBe(1)
        ->and($assignee->notifications()->count())->toBe(1);
});
