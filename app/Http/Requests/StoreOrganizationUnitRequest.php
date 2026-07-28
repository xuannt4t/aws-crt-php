<?php

namespace App\Http\Requests;

use App\Models\OrganizationUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', OrganizationUnit::class);
    }

    public function rules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_units', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('organization_units', 'code')],
            'is_active' => ['boolean'],
        ];
    }
}
