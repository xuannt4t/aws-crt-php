<?php

namespace App\Rules;

use App\Models\Project;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Từ chối gắn công việc vào một việc dự án mà người dùng không xem được. Dùng chung
 * cho StoreTaskRequest, UpdateTaskRequest, StoreTaskRecurrenceRequest và
 * UpdateTaskRecurrenceRequest để tránh lặp định nghĩa "việc dự án người dùng thấy
 * được" — nguồn sự thật duy nhất nằm ở Project::scopeVisibleTo(), cũng chính
 * là điều kiện phạm vi mà ProjectPolicy::view() hỏi lại.
 */
final class ProjectIsVisible implements ValidationRule
{
    public function __construct(private readonly User $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        $isVisible = Project::query()
            ->whereKey($value)
            ->visibleTo($this->user)
            ->exists();

        if (! $isVisible) {
            $fail('Bạn không có quyền gắn công việc vào việc dự án này.');
        }
    }
}
