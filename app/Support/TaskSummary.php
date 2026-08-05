<?php

namespace App\Support;

use App\Enums\TaskStatusBucket;
use App\Models\Task;
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
     *     earliest_created: ?string,
     *     latest_due: ?string,
     * }
     */
    public function for(Builder $query): array
    {
        $aggregateQuery = (clone $query)
            ->reorder()
            ->limit(null)
            ->offset(null);

        // `tasks` không có cột ngày bắt đầu riêng — `earliest_created` là mốc
        // gần đúng trung thực nhất (ngày tạo sớm nhất), không phải "ngày bắt
        // đầu" thực sự. Đặt tên khoá đúng như vậy để tránh Task 4 hiểu nhầm.
        $overdueExcludedStatuses = Task::overdueExcludedStatuses();
        $overdueExcludedPlaceholders = implode(', ', array_fill(0, count($overdueExcludedStatuses), '?'));

        // Cột đếm của mỗi ô dựng thẳng từ TaskStatusBucket, nên thêm trạng thái
        // vào một ô là cả phần đếm lẫn phần lọc cùng đổi theo.
        $bucketColumns = [];
        $bindings = [];

        foreach (TaskStatusBucket::cases() as $bucket) {
            $statuses = $bucket->statuses();
            $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
            $bucketColumns[] = "SUM(CASE WHEN status IN ({$placeholders}) THEN 1 ELSE 0 END) as {$bucket->value}";
            $bindings = [...$bindings, ...$statuses];
        }

        $bucketSql = implode(',
                ', $bucketColumns);

        /** @var object{total: int|string, not_started: int|string|null, in_progress: int|string|null, waiting_approval: int|string|null, completed: int|string|null, overdue: int|string|null, earliest_created: ?string, latest_due: ?string} $row */
        $row = $aggregateQuery->selectRaw(
            <<<SQL
                COUNT(*) as total,
                {$bucketSql},
                SUM(CASE WHEN due_at < ? AND status NOT IN ({$overdueExcludedPlaceholders}) THEN 1 ELSE 0 END) as overdue,
                MIN(created_at) as earliest_created,
                MAX(due_at) as latest_due
                SQL,
            [
                ...$bindings,
                now(),
                ...$overdueExcludedStatuses,
            ],
        )->first();

        return [
            'total' => (int) $row->total,
            'not_started' => (int) $row->not_started,
            'in_progress' => (int) $row->in_progress,
            'waiting_approval' => (int) $row->waiting_approval,
            'completed' => (int) $row->completed,
            'overdue' => (int) $row->overdue,
            'earliest_created' => $row->earliest_created,
            'latest_due' => $row->latest_due,
        ];
    }
}
