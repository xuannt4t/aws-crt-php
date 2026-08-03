<?php

namespace App\Support;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tóm tắt thống kê của khúc 1 (spec §6.1) — tính bằng MỘT truy vấn tổng hợp
 * trên cùng điều kiện của danh sách (phạm vi, bối cảnh, bộ lọc), trước khi
 * phân trang. Không phải sáu truy vấn đếm riêng, và không bao giờ đếm trên
 * trang hiện tại.
 */
final class TaskSummary
{
    /**
     * @return array{
     *     total: int,
     *     not_started: int,
     *     in_progress: int,
     *     waiting_approval: int,
     *     completed: int,
     *     overdue: int,
     *     earliest_start: ?string,
     *     latest_due: ?string,
     * }
     */
    public function for(Builder $query): array
    {
        $aggregateQuery = (clone $query)
            ->reorder()
            ->limit(null)
            ->offset(null);

        /** @var object{total: int|string, not_started: int|string|null, in_progress: int|string|null, waiting_approval: int|string|null, completed: int|string|null, overdue: int|string|null, earliest_start: ?string, latest_due: ?string} $row */
        $row = $aggregateQuery->selectRaw(
            <<<'SQL'
                COUNT(*) as total,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as not_started,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as waiting_approval,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN due_at < ? AND status NOT IN (?, ?) THEN 1 ELSE 0 END) as overdue,
                MIN(created_at) as earliest_start,
                MAX(due_at) as latest_due
                SQL,
            [
                TaskStatus::Draft->value,
                TaskStatus::Todo->value,
                TaskStatus::InProgress->value,
                TaskStatus::WaitingReview->value,
                TaskStatus::WaitingApproval->value,
                TaskStatus::Completed->value,
                now(),
                TaskStatus::Completed->value,
                TaskStatus::Cancelled->value,
            ],
        )->first();

        return [
            'total' => (int) $row->total,
            'not_started' => (int) $row->not_started,
            'in_progress' => (int) $row->in_progress,
            'waiting_approval' => (int) $row->waiting_approval,
            'completed' => (int) $row->completed,
            'overdue' => (int) $row->overdue,
            'earliest_start' => $row->earliest_start,
            'latest_due' => $row->latest_due,
        ];
    }
}
