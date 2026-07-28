<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTaskProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateProgress', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'progress' => ['required', 'integer', 'between:0,100'],
        ];
    }

    public function messages(): array
    {
        return [
            'progress.required' => 'Vui lòng nhập tiến độ công việc.',
            'progress.integer' => 'Tiến độ phải là số nguyên.',
            'progress.between' => 'Tiến độ phải nằm trong khoảng từ 0 đến 100%.',
        ];
    }
}
