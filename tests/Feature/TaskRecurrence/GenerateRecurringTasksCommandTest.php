<?php

use App\Actions\TaskRecurrence\GenerateTasksFromRecurrenceAction;
use App\Enums\ProjectStatus;
use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskRecurrence;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Carbon::setTestNow('2026-08-03 00:05:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('generates one task per due daily period', function () {
    $recurrence = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-07-30',
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $dates = Task::where('task_recurrence_id', $recurrence->id)
        ->orderBy('recurrence_date')
        ->pluck('recurrence_date')
        ->map(fn ($date) => $date->toDateString())
        ->all();

    expect($dates)->toBe(['2026-07-30', '2026-07-31', '2026-08-01', '2026-08-02', '2026-08-03']);
    expect($recurrence->fresh()->last_generated_for->toDateString())->toBe('2026-08-03');
});

test('generates one task per due weekly period', function () {
    $recurrence = TaskRecurrence::factory()->weekly([1, 3])->create([
        'start_date' => '2026-07-27', // Monday
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $dates = Task::where('task_recurrence_id', $recurrence->id)
        ->orderBy('recurrence_date')
        ->pluck('recurrence_date')
        ->map(fn ($date) => $date->toDateString())
        ->all();

    // Monday 2026-07-27, Wednesday 2026-07-29, Monday 2026-08-03
    expect($dates)->toBe(['2026-07-27', '2026-07-29', '2026-08-03']);
});

test('generates one task per due monthly period', function () {
    $recurrence = TaskRecurrence::factory()->monthly(3)->create([
        'start_date' => '2026-06-03',
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $dates = Task::where('task_recurrence_id', $recurrence->id)
        ->orderBy('recurrence_date')
        ->pluck('recurrence_date')
        ->map(fn ($date) => $date->toDateString())
        ->all();

    expect($dates)->toBe(['2026-06-03', '2026-07-03', '2026-08-03']);
});

test('generates one task per due quarterly period', function () {
    $recurrence = TaskRecurrence::factory()->quarterly(3)->create([
        'start_date' => '2026-02-03',
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $dates = Task::where('task_recurrence_id', $recurrence->id)
        ->orderBy('recurrence_date')
        ->pluck('recurrence_date')
        ->map(fn ($date) => $date->toDateString())
        ->all();

    expect($dates)->toBe(['2026-02-03', '2026-05-03', '2026-08-03']);
});

test('running the command twice on the same day does not create duplicates', function () {
    $recurrence = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-08-01',
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect(Task::where('task_recurrence_id', $recurrence->id)->count())->toBe(3);
});

test('backfills at most 30 periods per run and stops last_generated_for at the 30th period', function () {
    $recurrence = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-01-01',
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect(Task::where('task_recurrence_id', $recurrence->id)->count())
        ->toBe(GenerateTasksFromRecurrenceAction::MAX_PERIODS_PER_RUN);

    // 2026-01-01 + 29 days = 2026-01-30 (30th period, 0-indexed offset 29)
    expect($recurrence->fresh()->last_generated_for->toDateString())->toBe('2026-01-30');
});

test('inactive templates do not generate any task', function () {
    $recurrence = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-07-30',
        'is_active' => false,
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect(Task::where('task_recurrence_id', $recurrence->id)->count())->toBe(0);
    expect($recurrence->fresh()->last_generated_for)->toBeNull();
});

test('soft deleted templates do not generate any task', function () {
    $recurrence = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-07-30',
    ]);
    $recurrence->delete();

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect(Task::where('task_recurrence_id', $recurrence->id)->count())->toBe(0);
});

test('a template with an assignee produces todo tasks, otherwise draft', function () {
    $assignee = User::factory()->create();

    $withAssignee = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-08-03',
        'assignee_id' => $assignee->id,
    ]);
    $withoutAssignee = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-08-03',
        'assignee_id' => null,
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect(Task::where('task_recurrence_id', $withAssignee->id)->first()->status)->toBe(TaskStatus::Todo);
    expect(Task::where('task_recurrence_id', $withoutAssignee->id)->first()->status)->toBe(TaskStatus::Draft);
});

test('due_at combines the period date with due_time, defaulting to end of day', function () {
    $withTime = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-08-03',
        'due_time' => '14:30:00',
    ]);
    $withoutTime = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-08-03',
        'due_time' => null,
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $taskWithTime = Task::where('task_recurrence_id', $withTime->id)->first();
    $taskWithoutTime = Task::where('task_recurrence_id', $withoutTime->id)->first();

    expect($taskWithTime->due_at->format('Y-m-d H:i:s'))->toBe('2026-08-03 14:30:00');
    expect($taskWithoutTime->due_at->format('Y-m-d H:i:s'))->toBe('2026-08-03 23:59:59');
});

test('a template pointing at a closed project still generates a task, with project_id null and a warning logged', function () {
    Log::spy();

    $project = Project::factory()->create(['status' => ProjectStatus::Completed]);

    $recurrence = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-08-03',
        'project_id' => $project->id,
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $task = Task::where('task_recurrence_id', $recurrence->id)->first();

    expect($task)->not->toBeNull();
    expect($task->project_id)->toBeNull();

    Log::shouldHaveReceived('warning')->once();
});

test('each generated task records a created task activity', function () {
    $recurrence = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-08-01',
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $tasks = Task::where('task_recurrence_id', $recurrence->id)->get();

    expect($tasks)->toHaveCount(3);

    foreach ($tasks as $task) {
        expect($task->activities()->where('type', TaskActivityType::Created)->count())->toBe(1);
    }
});
