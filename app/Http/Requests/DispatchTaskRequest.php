<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DispatchTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('dispatch', $this->route('task'));
    }

    public function rules(): array
    {
        return [];
    }
}
