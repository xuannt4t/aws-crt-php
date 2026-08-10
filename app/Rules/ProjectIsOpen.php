<?php

namespace App\Rules;

use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Từ chối gắn công việc vào một việc dự án đã đóng (hoàn thành/huỷ). Dùng chung
 * cho StoreTaskRequest và UpdateTaskRequest để tránh lặp định nghĩa
 * "việc dự án đã đóng" — nguồn sự thật duy nhất nằm ở Project::isClosed()/
 * Project::closedStatusValues().
 */
final class ProjectIsOpen implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $isClosed = Project::query()
            ->whereKey($value)
            ->whereIn('status', Project::closedStatusValues())
            ->exists();

        if ($isClosed) {
            $fail('Không thể gắn công việc vào việc dự án đã đóng.');
        }
    }
}
