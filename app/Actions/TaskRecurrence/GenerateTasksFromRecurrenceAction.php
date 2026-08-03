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

        // Dự án của mẫu không đổi giữa các kỳ trong cùng một lần chạy, nên chỉ
        // phân giải (và cảnh báo) đúng một lần thay vì lặp lại mỗi kỳ.
        $projectId = $this->resolveProjectId($recurrence);

        return DB::transaction(function () use ($recurrence, $occurrences, $projectId): int {
            $created = 0;
            $lastOccurrence = null;

            foreach ($occurrences as $occurrence) {
                if ($this->generateTask($recurrence, $occurrence, $projectId)) {
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

    private function generateTask(TaskRecurrence $recurrence, CarbonImmutable $occurrence, ?int $projectId): bool
    {
        try {
            DB::transaction(function () use ($recurrence, $occurrence, $projectId): void {
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
            if ($this->isRecurrenceDuplicateViolation($e)) {
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

    /**
     * SQLSTATE 23000 là mã chung cho MỌI vi phạm ràng buộc toàn vẹn (unique,
     * not null, khoá ngoại...), nên không đủ để xác định đây đúng là trùng
     * cặp (task_recurrence_id, recurrence_date). Phải kiểm thêm mã lỗi riêng
     * của driver và nội dung thông báo trỏ đúng ràng buộc unique đó — lỗi
     * nào khác phải được ném lại để không âm thầm mất kỳ sinh.
     */
    private function isRecurrenceDuplicateViolation(QueryException $e): bool
    {
        $driverCode = $e->errorInfo[1] ?? null;
        $message = $e->getMessage();

        return match (true) {
            // MySQL: 1062 = ER_DUP_ENTRY, kèm đúng tên khoá unique của mình.
            $driverCode === 1062 => str_contains($message, 'task_recurrence_id_recurrence_date_unique'),
            // SQLite: 19 = SQLITE_CONSTRAINT, dùng chung cho UNIQUE/NOT NULL/FK
            // nên phải xác nhận thêm đúng cặp cột bị vi phạm.
            $driverCode === 19 => str_contains($message, 'UNIQUE constraint failed')
                && str_contains($message, 'tasks.task_recurrence_id')
                && str_contains($message, 'tasks.recurrence_date'),
            default => false,
        };
    }
}
