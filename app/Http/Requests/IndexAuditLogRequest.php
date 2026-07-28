<?php

namespace App\Http\Requests;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', AuditLog::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
