<?php

namespace App\Support;

use App\Enums\RecurrenceFrequency;
use App\Models\TaskRecurrence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class RecurrenceSchedule
{
    /**
     * Safety cap on the number of periods scanned, guarding against a
     * pathological `until` far in the future producing an unbounded loop.
     */
    private const MAX_ITERATIONS = 10000;

    private const WEEKDAY_LABELS = [
        1 => 'Thứ Hai',
        2 => 'Thứ Ba',
        3 => 'Thứ Tư',
        4 => 'Thứ Năm',
        5 => 'Thứ Sáu',
        6 => 'Thứ Bảy',
        7 => 'Chủ Nhật',
    ];

    /**
     * @return list<CarbonImmutable>
     */
    public function occurrencesBetween(TaskRecurrence $recurrence, CarbonInterface $from, CarbonInterface $until): array
    {
        $start = $this->toDate($recurrence->start_date);
        $from = $this->toDate($from)->max($start);
        $until = $this->toDate($until);

        if ($until->lessThan($from)) {
            return [];
        }

        return match ($recurrence->frequency) {
            RecurrenceFrequency::Daily => $this->dailyOccurrences($recurrence, $start, $from, $until),
            RecurrenceFrequency::Weekly => $this->weeklyOccurrences($recurrence, $start, $from, $until),
            RecurrenceFrequency::Monthly => $this->monthlyOccurrences($recurrence, $start, $from, $until, 1),
            RecurrenceFrequency::Quarterly => $this->monthlyOccurrences($recurrence, $start, $from, $until, 3),
        };
    }

    public function nextOccurrenceAfter(TaskRecurrence $recurrence, CarbonInterface $after): ?CarbonImmutable
    {
        $start = $this->toDate($recurrence->start_date);
        $searchFrom = $this->toDate($after)->addDay()->max($start);

        foreach ([2, 10, 20] as $horizonYears) {
            $occurrences = $this->occurrencesBetween($recurrence, $searchFrom, $searchFrom->addYears($horizonYears));

            if ($occurrences !== []) {
                return $occurrences[0];
            }
        }

        return null;
    }

    public function describe(TaskRecurrence $recurrence): string
    {
        $interval = $recurrence->interval;

        return match ($recurrence->frequency) {
            RecurrenceFrequency::Daily => $interval <= 1
                ? 'Hằng ngày'
                : "Mỗi {$interval} ngày",
            RecurrenceFrequency::Weekly => $this->describeWeekly($recurrence, $interval),
            RecurrenceFrequency::Monthly => $interval <= 1
                ? "Ngày {$recurrence->day_of_month} hằng tháng"
                : "Mỗi {$interval} tháng vào ngày {$recurrence->day_of_month}",
            RecurrenceFrequency::Quarterly => $interval <= 1
                ? "Ngày {$recurrence->day_of_month} hằng quý"
                : "Mỗi {$interval} quý vào ngày {$recurrence->day_of_month}",
        };
    }

    private function describeWeekly(TaskRecurrence $recurrence, int $interval): string
    {
        $weekdays = $recurrence->weekdays ?? [];
        sort($weekdays);

        $names = implode(', ', array_map(
            static fn (int $weekday): string => self::WEEKDAY_LABELS[$weekday] ?? '',
            $weekdays,
        ));

        return $interval <= 1
            ? "{$names} hằng tuần"
            : "Mỗi {$interval} tuần vào {$names}";
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function dailyOccurrences(TaskRecurrence $recurrence, CarbonImmutable $start, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $interval = max(1, $recurrence->interval);

        $daysFromStart = $start->diffInDays($from, false);
        $steps = (int) ceil(max(0, $daysFromStart) / $interval);
        $candidate = $start->addDays($steps * $interval);

        $dates = [];
        $iterations = 0;

        while ($candidate->lessThanOrEqualTo($until) && $iterations < self::MAX_ITERATIONS) {
            if ($candidate->greaterThanOrEqualTo($from)) {
                $dates[] = $candidate;
            }

            $candidate = $candidate->addDays($interval);
            $iterations++;
        }

        return $dates;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function weeklyOccurrences(TaskRecurrence $recurrence, CarbonImmutable $start, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $interval = max(1, $recurrence->interval);
        $weekdays = $recurrence->weekdays ?? [];
        sort($weekdays);

        if ($weekdays === []) {
            return [];
        }

        $week0Monday = $start->startOfWeek(CarbonInterface::MONDAY);
        $fromMonday = $from->startOfWeek(CarbonInterface::MONDAY);

        $weeksDiff = (int) ($week0Monday->diffInDays($fromMonday, false) / 7);
        $weeksDiff = max(0, $weeksDiff);

        $remainder = $weeksDiff % $interval;
        $currentMonday = $remainder === 0
            ? $fromMonday
            : $fromMonday->addWeeks($interval - $remainder);

        $dates = [];
        $iterations = 0;

        while ($currentMonday->lessThanOrEqualTo($until) && $iterations < self::MAX_ITERATIONS) {
            foreach ($weekdays as $weekday) {
                $date = $currentMonday->addDays($weekday - 1);

                if ($date->greaterThanOrEqualTo($from) && $date->lessThanOrEqualTo($until)) {
                    $dates[] = $date;
                }
            }

            $currentMonday = $currentMonday->addWeeks($interval);
            $iterations++;
        }

        return $dates;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function monthlyOccurrences(TaskRecurrence $recurrence, CarbonImmutable $start, CarbonImmutable $from, CarbonImmutable $until, int $monthMultiplier): array
    {
        $step = max(1, $recurrence->interval) * $monthMultiplier;
        $dayOfMonth = max(1, $recurrence->day_of_month ?? 1);

        $baseIndex = $start->year * 12 + $start->month - 1;
        $fromIndex = $from->year * 12 + $from->month - 1;

        $diff = max(0, $fromIndex - $baseIndex);
        $k = intdiv($diff, $step) * $step;

        $dates = [];
        $iterations = 0;

        while ($iterations < self::MAX_ITERATIONS) {
            $monthIndex = $baseIndex + $k;
            $year = intdiv($monthIndex, 12);
            $month = $monthIndex % 12 + 1;

            $tz = config('app.timezone');
            $daysInMonth = CarbonImmutable::createFromDate($year, $month, 1, $tz)->daysInMonth;
            $day = min($dayOfMonth, $daysInMonth);

            $candidate = CarbonImmutable::createFromDate($year, $month, $day, $tz)->startOfDay();

            if ($candidate->greaterThan($until)) {
                break;
            }

            if ($candidate->greaterThanOrEqualTo($from)) {
                $dates[] = $candidate;
            }

            $k += $step;
            $iterations++;
        }

        return $dates;
    }

    private function toDate(CarbonInterface $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)
            ->setTimezone(config('app.timezone'))
            ->startOfDay();
    }
}
