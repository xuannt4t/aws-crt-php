<?php

use App\Models\TaskRecurrence;
use App\Support\RecurrenceSchedule;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03 08:00:00'));
    config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function schedule(): RecurrenceSchedule
{
    return new RecurrenceSchedule;
}

// --- daily ---

test('daily occurrences fall every interval day starting from start_date', function () {
    $recurrence = TaskRecurrence::factory()->daily()->make([
        'start_date' => '2026-08-01',
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2026-08-01'),
        CarbonImmutable::parse('2026-08-05'),
    );

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))->toBe([
        '2026-08-01', '2026-08-02', '2026-08-03', '2026-08-04', '2026-08-05',
    ]);
});

test('daily occurrences respect interval greater than one', function () {
    $recurrence = TaskRecurrence::factory()->daily(3)->make([
        'start_date' => '2026-08-01',
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2026-08-01'),
        CarbonImmutable::parse('2026-08-10'),
    );

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))->toBe([
        '2026-08-01', '2026-08-04', '2026-08-07', '2026-08-10',
    ]);
});

// --- weekly ---

test('weekly occurrences generate one date per configured weekday', function () {
    // 2026-08-03 is a Monday.
    $recurrence = TaskRecurrence::factory()->weekly([1, 5])->make([
        'start_date' => '2026-08-03',
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2026-08-03'),
        CarbonImmutable::parse('2026-08-09'),
    );

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))->toBe([
        '2026-08-03', '2026-08-07',
    ]);
});

test('weekly occurrences with interval greater than one skip alternate weeks', function () {
    // 2026-08-03 is a Monday (week 0). Week 1 (2026-08-10) should be skipped.
    $recurrence = TaskRecurrence::factory()->weekly([1])->make([
        'start_date' => '2026-08-03',
        'interval' => 2,
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2026-08-03'),
        CarbonImmutable::parse('2026-08-24'),
    );

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))->toBe([
        '2026-08-03', '2026-08-17',
    ]);
});

test('weekly occurrences never fall before start_date even when the week already started', function () {
    // Week containing start_date runs Mon 2026-08-03..Sun 2026-08-09.
    // start_date itself is Wednesday 2026-08-05, so Monday 2026-08-03 must not be emitted.
    $recurrence = TaskRecurrence::factory()->weekly([1, 3])->make([
        'start_date' => '2026-08-05',
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2026-08-01'),
        CarbonImmutable::parse('2026-08-09'),
    );

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))->toBe([
        '2026-08-05',
    ]);
});

// --- monthly ---

test('monthly occurrences fall on day_of_month for each interval month', function () {
    $recurrence = TaskRecurrence::factory()->monthly(5)->make([
        'start_date' => '2026-01-05',
        'interval' => 2,
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2026-01-01'),
        CarbonImmutable::parse('2026-07-31'),
    );

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))->toBe([
        '2026-01-05', '2026-03-05', '2026-05-05', '2026-07-05',
    ]);
});

test('monthly occurrences with day_of_month 31 fall back to the last day of a 30-day month', function () {
    $recurrence = TaskRecurrence::factory()->monthly(31)->make([
        'start_date' => '2026-01-31',
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2026-04-01'),
        CarbonImmutable::parse('2026-04-30'),
    );

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))->toBe([
        '2026-04-30',
    ]);
});

test('monthly occurrences with day_of_month 31 fall back to 28 in a common year February', function () {
    $recurrence = TaskRecurrence::factory()->monthly(31)->make([
        'start_date' => '2026-01-31',
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2026-02-01'),
        CarbonImmutable::parse('2026-02-28'),
    );

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))->toBe([
        '2026-02-28',
    ]);
});

test('monthly occurrences with day_of_month 31 fall back to 29 in a leap year February', function () {
    $recurrence = TaskRecurrence::factory()->monthly(31)->make([
        'start_date' => '2024-01-31',
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2024-02-01'),
        CarbonImmutable::parse('2024-02-29'),
    );

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))->toBe([
        '2024-02-29',
    ]);
});

// --- quarterly ---

test('quarterly occurrences fall every interval times three months on day_of_month', function () {
    $recurrence = TaskRecurrence::factory()->quarterly(15)->make([
        'start_date' => '2026-01-15',
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2026-01-01'),
        CarbonImmutable::parse('2026-12-31'),
    );

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))->toBe([
        '2026-01-15', '2026-04-15', '2026-07-15', '2026-10-15',
    ]);
});

test('quarterly occurrences respect interval greater than one', function () {
    $recurrence = TaskRecurrence::factory()->quarterly(15)->make([
        'start_date' => '2026-01-15',
        'interval' => 2,
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2026-01-01'),
        CarbonImmutable::parse('2026-12-31'),
    );

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))->toBe([
        '2026-01-15', '2026-07-15',
    ]);
});

// --- boundaries ---

test('occurrences never fall earlier than start_date even when from is earlier', function () {
    $recurrence = TaskRecurrence::factory()->daily()->make([
        'start_date' => '2026-08-05',
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2026-08-01'),
        CarbonImmutable::parse('2026-08-07'),
    );

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))->toBe([
        '2026-08-05', '2026-08-06', '2026-08-07',
    ]);
});

test('the from and until interval is closed at both ends', function () {
    $recurrence = TaskRecurrence::factory()->daily()->make([
        'start_date' => '2026-08-01',
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2026-08-02'),
        CarbonImmutable::parse('2026-08-04'),
    );

    expect(array_map(fn (CarbonImmutable $d) => $d->toDateString(), $dates))->toBe([
        '2026-08-02', '2026-08-03', '2026-08-04',
    ]);
});

test('occurrences between returns an empty list when until is before the effective start', function () {
    $recurrence = TaskRecurrence::factory()->daily()->make([
        'start_date' => '2026-08-05',
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2026-08-01'),
        CarbonImmutable::parse('2026-08-04'),
    );

    expect($dates)->toBe([]);
});

test('occurrences between terminates sensibly for a far future until bound', function () {
    $recurrence = TaskRecurrence::factory()->daily(1)->make([
        'start_date' => '2026-08-01',
    ]);

    $dates = schedule()->occurrencesBetween(
        $recurrence,
        CarbonImmutable::parse('2026-08-01'),
        CarbonImmutable::parse('2126-08-01'),
    );

    expect($dates)->not->toBeEmpty()
        ->and($dates[0]->toDateString())->toBe('2026-08-01');
});

// --- next occurrence after ---

test('next occurrence after returns the next due date strictly after the given moment', function () {
    $recurrence = TaskRecurrence::factory()->daily()->make([
        'start_date' => '2026-08-01',
    ]);

    $next = schedule()->nextOccurrenceAfter($recurrence, CarbonImmutable::parse('2026-08-03'));

    expect($next?->toDateString())->toBe('2026-08-04');
});

test('next occurrence after skips the current occurrence on the same day', function () {
    $recurrence = TaskRecurrence::factory()->weekly([1])->make([
        'start_date' => '2026-08-03',
    ]);

    $next = schedule()->nextOccurrenceAfter($recurrence, CarbonImmutable::parse('2026-08-03 00:00:00'));

    expect($next?->toDateString())->toBe('2026-08-10');
});

test('next occurrence after never returns a date earlier than start_date', function () {
    $recurrence = TaskRecurrence::factory()->daily()->make([
        'start_date' => '2026-08-05',
    ]);

    $next = schedule()->nextOccurrenceAfter($recurrence, CarbonImmutable::parse('2026-08-01'));

    expect($next?->toDateString())->toBe('2026-08-05');
});

// --- describe ---

test('describe formats daily recurrences', function () {
    $daily = TaskRecurrence::factory()->daily()->make(['start_date' => '2026-08-01']);
    $daily3 = TaskRecurrence::factory()->daily(3)->make(['start_date' => '2026-08-01']);

    expect(schedule()->describe($daily))->toBe('Hằng ngày')
        ->and(schedule()->describe($daily3))->toBe('Mỗi 3 ngày');
});

test('describe formats weekly recurrences', function () {
    $singleDay = TaskRecurrence::factory()->weekly([1])->make(['start_date' => '2026-08-03']);
    $multiDay = TaskRecurrence::factory()->weekly([1, 5])->make(['start_date' => '2026-08-03']);
    $everyOtherWeek = TaskRecurrence::factory()->weekly([1])->make([
        'start_date' => '2026-08-03',
        'interval' => 2,
    ]);

    expect(schedule()->describe($singleDay))->toBe('Thứ Hai hằng tuần')
        ->and(schedule()->describe($multiDay))->toBe('Thứ Hai, Thứ Sáu hằng tuần')
        ->and(schedule()->describe($everyOtherWeek))->toBe('Mỗi 2 tuần vào Thứ Hai');
});

test('describe formats monthly recurrences', function () {
    $monthly = TaskRecurrence::factory()->monthly(5)->make(['start_date' => '2026-01-05']);
    $everyOtherMonth = TaskRecurrence::factory()->monthly(5)->make([
        'start_date' => '2026-01-05',
        'interval' => 2,
    ]);

    expect(schedule()->describe($monthly))->toBe('Ngày 5 hằng tháng')
        ->and(schedule()->describe($everyOtherMonth))->toBe('Mỗi 2 tháng vào ngày 5');
});

test('describe formats quarterly recurrences', function () {
    $quarterly = TaskRecurrence::factory()->quarterly(15)->make(['start_date' => '2026-01-15']);
    $everyOtherQuarter = TaskRecurrence::factory()->quarterly(15)->make([
        'start_date' => '2026-01-15',
        'interval' => 2,
    ]);

    expect(schedule()->describe($quarterly))->toBe('Ngày 15 hằng quý')
        ->and(schedule()->describe($everyOtherQuarter))->toBe('Mỗi 2 quý vào ngày 15');
});
