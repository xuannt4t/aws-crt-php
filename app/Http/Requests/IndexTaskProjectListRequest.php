<?php

namespace App\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cấp 1 của "Việc dự án" (spec §5.2) — danh sách dự án, không phải danh sách
 * việc, nên chỉ cần bộ lọc từ khoá. Cổng quyền vẫn là Task::viewAny vì đây là
 * lối vào của module công việc, giống IndexTaskRequest.
 */
final class IndexTaskProjectListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Task::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
        ];
    }
}
