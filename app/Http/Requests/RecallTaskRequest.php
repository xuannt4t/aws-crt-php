<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RecallTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recall', $this->route('task'));
    }

    public function rules(): array
    {
        return [];
    }
}
