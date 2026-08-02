<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'organization_unit_id' => [
                'required',
                'integer',
                Rule::exists('organization_units', 'id')->whereNull('deleted_at'),
            ],
            'owner_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_\-]+$/',
                Rule::unique('projects', 'code')->ignore($this->route('project')),
            ],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Mã dự án chỉ được chứa chữ hoa, số, dấu gạch dưới và gạch ngang.',
            'code.unique' => 'Mã dự án đã tồn tại.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ];
    }
}
