<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        return [
            'organization_unit_id' => [
                'required',
                'integer',
                Rule::exists('organization_units', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'employee_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users', 'employee_code')->ignore($this->route('user')),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'roles' => [
                Rule::prohibitedIf(fn () => ! $this->user()->can(PermissionName::UserAssignRole->value)),
                'sometimes',
                'array',
                'min:1',
            ],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where('guard_name', 'web'),
            ],
            'is_system_admin' => ['prohibited'],
        ];
    }
}
