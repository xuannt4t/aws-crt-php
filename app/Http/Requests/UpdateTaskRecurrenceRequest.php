<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use App\Rules\ProjectIsOpen;
use App\Rules\ProjectIsVisible;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTaskRecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('taskRecurrence'));
    }

    public function rules(): array
    {
        $isWeekly = $this->input('frequency') === RecurrenceFrequency::Weekly->value;
        $needsDayOfMonth = in_array($this->input('frequency'), [
            RecurrenceFrequency::Monthly->value,
            RecurrenceFrequency::Quarterly->value,
        ], true);

        return [
            'organization_unit_id' => [
                'required',
                'integer',
                Rule::exists('organization_units', 'id')->whereNull('deleted_at'),
            ],
            'project_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id')->whereNull('deleted_at'),
                new ProjectIsVisible($this->user()),
                new ProjectIsOpen,
            ],
            'assignee_id' => [
                Rule::prohibitedIf(fn () => ! $this->user()->can(PermissionName::TaskAssign->value)
                    && $this->filled('assignee_id')
                    && $this->integer('assignee_id') !== $this->user()->id),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('is_active', true)),
            ],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'planned_quantity' => ['nullable', 'integer', 'min:1'],
            'quantity_unit' => [
                Rule::requiredIf(fn (): bool => $this->filled('planned_quantity')),
                'nullable',
                'string',
                'max:32',
            ],
            'frequency' => ['required', Rule::enum(RecurrenceFrequency::class)],
            'interval' => ['required', 'integer', 'min:1', 'max:52'],
            'weekdays' => [
                Rule::requiredIf($isWeekly),
                Rule::prohibitedIf(! $isWeekly),
                'array',
                'min:1',
            ],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'day_of_month' => [
                Rule::requiredIf($needsDayOfMonth),
                Rule::prohibitedIf(! $needsDayOfMonth),
                'integer',
                'between:1,31',
            ],
            'start_date' => ['required', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'weekdays.required' => 'Vui lòng chọn ít nhất một thứ trong tuần.',
            'weekdays.prohibited' => 'Chỉ chọn thứ trong tuần khi kiểu lặp là hằng tuần.',
            'weekdays.min' => 'Vui lòng chọn ít nhất một thứ trong tuần.',
            'weekdays.*.between' => 'Thứ trong tuần phải từ 1 (Thứ Hai) đến 7 (Chủ Nhật).',
            'weekdays.*.distinct' => 'Không được chọn trùng thứ trong tuần.',
            'day_of_month.required' => 'Vui lòng chọn ngày trong tháng.',
            'day_of_month.prohibited' => 'Chỉ chọn ngày trong tháng khi kiểu lặp là hằng tháng hoặc hằng quý.',
            'day_of_month.between' => 'Ngày trong tháng phải từ 1 đến 31.',
            'quantity_unit.required' => 'Vui lòng nhập đơn vị khi có số lượng dự kiến.',
        ];
    }
}
