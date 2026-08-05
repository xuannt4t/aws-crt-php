<?php

namespace App\Enums;

/**
 * Nhóm trạng thái đứng sau các ô "Tóm tắt thống kê" (spec §6.1).
 *
 * Định nghĩa DUY NHẤT của việc một ô gồm những trạng thái nào. `TaskSummary`
 * dùng nó để đếm, còn bộ lọc của danh sách dùng nó để lọc — nhờ vậy bấm vào ô
 * "Chưa làm" chắc chắn ra đúng số việc mà ô đó đang hiển thị.
 *
 * Nếu bộ lọc tự liệt kê lại `draft` + `todo` ở một chỗ khác, chỉ cần sau này
 * thêm một trạng thái vào ô mà quên sửa chỗ kia là số trên thẻ và số dòng lọc
 * được sẽ lệch nhau, mà không có gì báo lỗi.
 */
enum TaskStatusBucket: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case WaitingApproval = 'waiting_approval';
    case Completed = 'completed';

    /**
     * @return list<string>
     */
    public function statuses(): array
    {
        return array_map(
            static fn (TaskStatus $status): string => $status->value,
            match ($this) {
                self::NotStarted => [TaskStatus::Draft, TaskStatus::Todo],
                self::InProgress => [TaskStatus::InProgress],
                self::WaitingApproval => [TaskStatus::WaitingReview],
                self::Completed => [TaskStatus::Completed],
            },
        );
    }
}
