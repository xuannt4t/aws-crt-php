<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Draft = 'draft';
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case WaitingReview = 'waiting_review';
    case WaitingApproval = 'waiting_approval';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
