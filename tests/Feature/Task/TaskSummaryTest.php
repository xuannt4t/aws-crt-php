<?php

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Support\TaskSummary;
use Illuminate\Support\Carbon;

test('summary counts each status bucket exactly once and cancelled lands in none', function () {
    Task::factory()->create(['status' => TaskStatus::Draft]);
    Task::factory()->create(['status' => TaskStatus::Todo]);
    Task::factory()->count(2)->create(['status' => TaskStatus::InProgress]);
    Task::factory()->create(['status' => TaskStatus::WaitingReview]);
    Task::factory()->create(['status' => TaskStatus::WaitingApproval]);
    Task::factory()->count(3)->create(['status' => TaskStatus::Completed]);
    Task::factory()->create(['status' => TaskStatus::Cancelled]);

    $summary = app(TaskSummary::class)->for(Task::query());

    expect($summary['total'])->toBe(10)
        ->and($summary['not_started'])->toBe(2)
        ->and($summary['in_progress'])->toBe(2)
        ->and($summary['waiting_approval'])->toBe(2)
        ->and($summary['completed'])->toBe(3)
        ->and($summary['not_started'] + $summary['in_progress'] + $summary['waiting_approval'] + $summary['completed'])
        ->toBeLessThan($summary['total']);
});

test('the overdue counter cuts across the other status buckets', function () {
    Task::factory()->create([
        'status' => TaskStatus::InProgress,
        'due_at' => now()->subDay(),
    ]);
    Task::factory()->create([
        'status' => TaskStatus::Todo,
        'due_at' => now()->subDay(),
    ]);
    Task::factory()->create([
        'status' => TaskStatus::Completed,
        'due_at' => now()->subDay(),
    ]);
    Task::factory()->create([
        'status' => TaskStatus::InProgress,
        'due_at' => now()->addDay(),
    ]);

    $summary = app(TaskSummary::class)->for(Task::query());

    expect($summary['overdue'])->toBe(2);
});

test('summary is computed over the whole filtered set, not just the current page', function () {
    Task::factory()->count(25)->create(['status' => TaskStatus::Todo]);

    $query = Task::query()->where('status', TaskStatus::Todo->value);

    $paginated = (clone $query)->paginate(20);
    $summary = app(TaskSummary::class)->for($query);

    expect($paginated->count())->toBe(20)
        ->and($summary['total'])->toBe(25)
        ->and($summary['not_started'])->toBe(25);
});

test('earliest_created and latest_due reflect the filtered set', function () {
    $earliest = Carbon::parse('2026-01-01 00:00:00');
    $latest = Carbon::parse('2026-12-31 00:00:00');

    Task::factory()->create(['created_at' => $earliest, 'due_at' => now()->addDays(3)]);
    Task::factory()->create(['created_at' => now(), 'due_at' => $latest]);
    Task::factory()->create(['created_at' => now()->subDays(1), 'due_at' => now()->addDays(1)]);

    $summary = app(TaskSummary::class)->for(Task::query());

    expect(Carbon::parse($summary['earliest_created'])->equalTo($earliest))->toBeTrue()
        ->and(Carbon::parse($summary['latest_due'])->equalTo($latest))->toBeTrue();
});

test('the overdue counter stays pinned to Task::scopeOverdue, including boundary statuses', function () {
    // Khoá ô "trễ hạn" chống trôi lệch âm thầm: cả hai định nghĩa giờ dùng
    // chung Task::overdueExcludedStatuses(), nhưng test này còn so trực tiếp
    // số đếm gộp với kết quả thật của scopeOverdue() trên cùng tập đã lọc,
    // để mọi sai khác trong tương lai làm test này thất bại ngay.
    Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00'));

    Task::factory()->create([
        'status' => TaskStatus::InProgress,
        'due_at' => now(), // due exactly now — not strictly in the past, not overdue
    ]);
    Task::factory()->create([
        'status' => TaskStatus::Cancelled,
        'due_at' => now()->subDay(), // past due but cancelled — excluded
    ]);
    Task::factory()->create([
        'status' => TaskStatus::Completed,
        'due_at' => now()->subDay(), // past due but completed — excluded
    ]);
    Task::factory()->create([
        'status' => TaskStatus::InProgress,
        'due_at' => now()->subDay(), // past due and still active — overdue
    ]);

    $query = Task::query();
    $expectedOverdue = (clone $query)->overdue()->count();

    $summary = app(TaskSummary::class)->for($query);

    expect($summary['overdue'])->toBe($expectedOverdue)
        ->and($summary['overdue'])->toBe(1);

    Carbon::setTestNow();
});

test('a query with an existing order and limit does not corrupt the aggregate', function () {
    Task::factory()->count(5)->create(['status' => TaskStatus::Todo]);

    $query = Task::query()->where('status', TaskStatus::Todo->value)->latest('id')->limit(2);

    $summary = app(TaskSummary::class)->for($query);

    expect($summary['total'])->toBe(5);
});
