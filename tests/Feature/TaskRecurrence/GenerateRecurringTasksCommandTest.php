<?php

use App\Actions\TaskRecurrence\GenerateTasksFromRecurrenceAction;
use App\Actions\TaskRecurrence\ToggleTaskRecurrenceAction;
use App\Enums\ProjectStatus;
use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskRecurrence;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
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

test('running the command twice on the same day short-circuits with no new occurrences', function () {
    $recurrence = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-08-01',
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect(Task::where('task_recurrence_id', $recurrence->id)->count())->toBe(3);
});

test('a colliding task for an already-due period is skipped, the rest are generated, and last_generated_for still advances', function () {
    $recurrence = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-08-01',
    ]);

    // Pre-insert a task that collides with the (task_recurrence_id, recurrence_date)
    // pair the run is about to generate for 2026-08-02, simulating a period that
    // was already generated out of band (e.g. a previous run partially committed
    // before crashing). last_generated_for is left untouched, so the run still
    // tries to (re)generate 2026-08-01, 2026-08-02 and 2026-08-03.
    Task::factory()->create([
        'organization_unit_id' => $recurrence->organization_unit_id,
        'creator_id' => $recurrence->creator_id,
        'task_recurrence_id' => $recurrence->id,
        'recurrence_date' => '2026-08-02',
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $dates = Task::where('task_recurrence_id', $recurrence->id)
        ->orderBy('recurrence_date')
        ->pluck('recurrence_date')
        ->map(fn ($date) => $date->toDateString())
        ->all();

    // Exactly one row per period: the pre-existing 2026-08-02 row was kept as-is
    // (the run's attempt to insert a duplicate for that date was caught and
    // skipped), while 2026-08-01 and 2026-08-03 were newly generated.
    expect($dates)->toBe(['2026-08-01', '2026-08-02', '2026-08-03']);
    expect(Task::where('task_recurrence_id', $recurrence->id)->count())->toBe(3);
    expect($recurrence->fresh()->last_generated_for->toDateString())->toBe('2026-08-03');
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
        'start_date' => '2026-08-01',
        'project_id' => $project->id,
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $tasks = Task::where('task_recurrence_id', $recurrence->id)->get();

    expect($tasks)->toHaveCount(3);
    expect($tasks->pluck('project_id')->unique()->all())->toBe([null]);

    // Việc dự án được phân giải một lần cho cả lượt chạy, không phải mỗi kỳ một lần.
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

test('re-enabling a paused template does not back-fill the periods missed while it was off', function () {
    $recurrence = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-08-01',
    ]);

    // Chạy bình thường tới 2026-08-03.
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect(Task::where('task_recurrence_id', $recurrence->id)->count())->toBe(3);

    // Tắt mẫu, để hệ thống chạy tiếp nhiều kỳ.
    $toggle = app(ToggleTaskRecurrenceAction::class);
    $actor = User::find($recurrence->creator_id);
    $toggle->execute($actor, $recurrence);

    Carbon::setTestNow('2026-08-10 00:05:00');
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect(Task::where('task_recurrence_id', $recurrence->id)->count())->toBe(3);

    // Bật lại vào 2026-08-10 rồi chạy: chỉ các kỳ TỪ ngày bật lại trở đi được sinh.
    $toggle->execute($actor, $recurrence);

    Carbon::setTestNow('2026-08-11 00:05:00');
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $dates = Task::where('task_recurrence_id', $recurrence->id)
        ->orderBy('recurrence_date')
        ->pluck('recurrence_date')
        ->map(fn ($date) => $date->toDateString())
        ->all();

    expect($dates)->toBe(['2026-08-01', '2026-08-02', '2026-08-03', '2026-08-10', '2026-08-11']);
});

test('moving start_date into the past does not back-fill periods before last_generated_for', function () {
    $recurrence = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-08-01',
    ]);

    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    expect($recurrence->fresh()->last_generated_for->toDateString())->toBe('2026-08-03');

    // Kéo start_date lùi rất xa: mốc sinh vẫn là last_generated_for + 1 ngày.
    $recurrence->update(['start_date' => '2026-01-01']);

    Carbon::setTestNow('2026-08-04 00:05:00');
    $this->artisan('tasks:generate-recurring')->assertSuccessful();

    $dates = Task::where('task_recurrence_id', $recurrence->id)
        ->orderBy('recurrence_date')
        ->pluck('recurrence_date')
        ->map(fn ($date) => $date->toDateString())
        ->all();

    expect($dates)->toBe(['2026-08-01', '2026-08-02', '2026-08-03', '2026-08-04']);
});

test('a template that throws is logged and skipped without stopping the rest of the run', function () {
    Log::spy();

    $failing = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-08-03',
        'title' => 'Mẫu gây lỗi',
    ]);
    $healthy = TaskRecurrence::factory()->daily()->create([
        'start_date' => '2026-08-03',
        'title' => 'Mẫu bình thường',
    ]);

    // Mô phỏng một lỗi KHÔNG phải trùng khoá (event/observer hỏng, ràng buộc
    // độ dài cột...) khi ghi công việc của riêng mẫu đầu tiên.
    Event::listen('eloquent.creating: '.Task::class, function (Task $task): void {
        if ($task->title === 'Mẫu gây lỗi') {
            throw new RuntimeException('Sự cố khi sinh công việc.');
        }
    });

    // Mẫu hỏng không được chặn mẫu đứng sau nó, nhưng lệnh phải báo thất bại.
    $this->artisan('tasks:generate-recurring')->assertFailed();

    expect($healthy->id)->toBeGreaterThan($failing->id);
    expect(Task::where('task_recurrence_id', $failing->id)->count())->toBe(0);
    expect(Task::where('task_recurrence_id', $healthy->id)->count())->toBe(1);
    expect($failing->fresh()->last_generated_for)->toBeNull();
    expect($healthy->fresh()->last_generated_for->toDateString())->toBe('2026-08-03');

    Log::shouldHaveReceived('error')->once();
});

test('the scheduler runs the recurring task generator daily at 00:05', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', 'tasks:generate-recurring'));

    expect($events)->toHaveCount(1);
    expect($events->first()->expression)->toBe('5 0 * * *');
});
