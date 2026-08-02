<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Models\Project;
use Closure;
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
            'project_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->whereNull('deleted_at'),
                $this->rejectClosedProject(),
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
            'planned_quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'quantity_unit' => [
                Rule::prohibitedIf(fn (): bool => ! $this->filled('planned_quantity')),
                'nullable',
                'string',
                'max:30',
            ],
            'actual_quantity' => ['prohibited'],
            'status' => ['prohibited'],
            'progress' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'creator_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'planned_quantity.integer' => 'Số lượng dự kiến phải là số nguyên.',
            'planned_quantity.min' => 'Số lượng dự kiến phải lớn hơn 0.',
            'planned_quantity.max' => 'Số lượng dự kiến không được vượt quá 1.000.000.',
            'quantity_unit.prohibited' => 'Chỉ nhập đơn vị khi công việc có số lượng dự kiến.',
            'quantity_unit.max' => 'Đơn vị không được dài quá 30 ký tự.',
            'actual_quantity.prohibited' => 'Số lượng đã làm chỉ được cập nhật ở trang chi tiết công việc.',
        ];
    }

    private function rejectClosedProject(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null) {
                return;
            }

            $isClosed = Project::query()
                ->whereKey($value)
                ->whereIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])
                ->exists();

            if ($isClosed) {
                $fail('Không thể gắn công việc vào dự án đã đóng.');
            }
        };
    }
}
