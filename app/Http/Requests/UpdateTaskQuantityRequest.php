<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTaskQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateProgress', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'actual_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'actual_quantity.required' => 'Vui lòng nhập số lượng đã làm.',
            'actual_quantity.integer' => 'Số lượng đã làm phải là số nguyên.',
            'actual_quantity.min' => 'Số lượng đã làm không được là số âm.',
            'actual_quantity.max' => 'Số lượng đã làm không được vượt quá 1.000.000.',
        ];
    }
}
