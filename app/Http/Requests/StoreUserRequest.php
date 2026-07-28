<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_code')],
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
