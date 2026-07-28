<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
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
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'organization_unit_id' => ['nullable', 'integer', Rule::exists('organization_units', 'id')->whereNull('deleted_at')],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'overdue' => ['nullable', 'boolean'],
        ];
    }
}
