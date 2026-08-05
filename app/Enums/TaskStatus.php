<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Draft = 'draft';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    // Luồng duyệt một cấp: nộp xong là chờ kiểm tra, người duyệt chốt thẳng sang
    // hoàn thành hoặc trả lại. Không có trạng thái "chờ phê duyệt" trung gian.
    case WaitingReview = 'waiting_review';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
