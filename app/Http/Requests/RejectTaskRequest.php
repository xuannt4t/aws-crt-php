<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RejectTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reject', $this->route('task'));
    }

    /**
     * Trả lại thì lý do là bắt buộc: người làm phải biết cần sửa gì, chứ nhận
     * một việc bị đẩy ngược về "đang làm" mà không kèm giải thích thì vô nghĩa.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => 'lý do trả lại'];
    }
}
