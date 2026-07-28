<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StartTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('start', $this->route('task'));
    }

    public function rules(): array
    {
        return [];
    }
}
