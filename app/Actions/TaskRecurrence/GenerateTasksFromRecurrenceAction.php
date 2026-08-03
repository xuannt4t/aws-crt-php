<?php

namespace App\Actions\TaskRecurrence;

use App\Actions\Task\RecordTaskActivityAction;
use App\Enums\TaskActivityType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskRecurrence;
use App\Support\RecurrenceSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class GenerateTasksFromRecurrenceAction
{
    /**
     * Giới hạn số kỳ được sinh cho một mẫu trong một lần chạy, để hệ thống
     * ngừng lâu thì sinh bù dần qua các ngày thay vì nổ dữ liệu một lần.
     */
    public const MAX_PERIODS_PER_RUN = 30;

    public function __construct(
        private readonly RecurrenceSchedule $schedule,
        private readonly RecordTaskActivityAction $recordActivity,
    ) {}

    public function execute(TaskRecurrence $recurrence, CarbonInterface $until): int
    {
        $from = $recurrence->last_generated_for !== null
            ? CarbonImmutable::parse($recurrence->last_generated_for)->addDay()
            : CarbonImmutable::parse($recurrence->start_date);

        $occurrences = array_slice(
            $this->schedule->occurrencesBetween($recurrence, $from, CarbonImmutable::parse($until)),
            0,
            self::MAX_PERIODS_PER_RUN,
        );

        if ($occurrences === []) {
            return 0;
        }

        return DB::transaction(function () use ($recurrence, $occurrences): int {
            $created = 0;
            $lastOccurrence = null;

            foreach ($occurrences as $occurrence) {
                if ($this->generateTask($recurrence, $occurrence)) {
                    $created++;
                }

                $lastOccurrence = $occurrence;
            }

            if ($lastOccurrence !== null) {
                $recurrence->forceFill(['last_generated_for' => $lastOccurrence->toDateString()])->save();
            }

            return $created;
        });
    }

    private function generateTask(TaskRecurrence $recurrence, CarbonImmutable $occurrence): bool
    {
        try {
            DB::transaction(function () use ($recurrence, $occurrence): void {
                $projectId = $this->resolveProjectId($recurrence);
                $plannedQuantity = $recurrence->planned_quantity;

                $task = Task::create([
                    'organization_unit_id' => $recurrence->organization_unit_id,
                    'project_id' => $projectId,
                    'creator_id' => $recurrence->creator_id,
                    'assignee_id' => $recurrence->assignee_id,
                    'title' => $recurrence->title,
                    'description' => $recurrence->description,
                    'status' => $recurrence->assignee_id !== null ? TaskStatus::Todo : TaskStatus::Draft,
                    'priority' => $recurrence->priority,
                    'progress' => 0,
                    'planned_quantity' => $plannedQuantity,
                    'actual_quantity' => $plannedQuantity !== null ? 0 : null,
                    'quantity_unit' => $recurrence->quantity_unit,
                    'due_at' => $this->resolveDueAt($recurrence, $occurrence),
                    'task_recurrence_id' => $recurrence->id,
                    'recurrence_date' => $occurrence->toDateString(),
                ]);

                $this->recordActivity->execute($recurrence->creator, $task, TaskActivityType::Created);
            });

            return true;
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                return false;
            }

            throw $e;
        }
    }

    private function resolveProjectId(TaskRecurrence $recurrence): ?int
    {
        if ($recurrence->project_id === null) {
            return null;
        }

        $project = Project::withTrashed()->find($recurrence->project_id);

        if ($project === null || $project->trashed() || $project->isClosed()) {
            Log::warning('Mẫu công việc định kỳ trỏ tới dự án không còn hợp lệ để sinh công việc, bỏ qua dự án.', [
                'task_recurrence_id' => $recurrence->id,
                'project_id' => $recurrence->project_id,
            ]);

            return null;
        }

        return $project->id;
    }

    private function resolveDueAt(TaskRecurrence $recurrence, CarbonImmutable $occurrence): CarbonImmutable
    {
        $date = $occurrence->toDateString();

        if ($recurrence->due_time !== null) {
            return CarbonImmutable::parse("{$date} {$recurrence->due_time}", config('app.timezone'));
        }

        return CarbonImmutable::parse("{$date} 23:59:59", config('app.timezone'));
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        return $e->getCode() === '23000';
    }
}
