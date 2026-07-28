<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Enums\TaskPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'organization_unit_id' => [
                'required',
                'integer',
                Rule::exists('organization_units', 'id')->whereNull('deleted_at'),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')
                    ->whereNull('deleted_at')
                    ->where('id', '!=', $this->route('task')->id),
            ],
            'assignee_id' => [
                Rule::prohibitedIf(fn () => ! $this->user()->can(PermissionName::TaskAssign->value)),
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('is_active', true)),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'due_at' => ['nullable', 'date'],
            'status' => ['prohibited'],
            'progress' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'creator_id' => ['prohibited'],
        ];
    }
}
