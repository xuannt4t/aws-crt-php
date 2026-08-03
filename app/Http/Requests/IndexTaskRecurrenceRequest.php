<?php

namespace App\Http\Requests;

use App\Enums\RecurrenceFrequency;
use App\Models\TaskRecurrence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexTaskRecurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', TaskRecurrence::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'organization_unit_id' => ['nullable', 'integer', Rule::exists('organization_units', 'id')->whereNull('deleted_at')],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'frequency' => ['nullable', Rule::enum(RecurrenceFrequency::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
