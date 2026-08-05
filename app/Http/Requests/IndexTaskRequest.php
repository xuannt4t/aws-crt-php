<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskStatusBucket;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Task::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            // Ô tóm tắt bấm được: lọc theo nhóm trạng thái của ô, không phải một
            // trạng thái đơn lẻ — ô "Chưa làm" gộp nháp và cần làm.
            'bucket' => ['nullable', Rule::enum(TaskStatusBucket::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'organization_unit_id' => ['nullable', 'integer', Rule::exists('organization_units', 'id')->whereNull('deleted_at')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'assignee_ids' => ['nullable', 'array', 'max:50'],
            'assignee_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'overdue' => ['nullable', 'boolean'],
        ];
    }
}
