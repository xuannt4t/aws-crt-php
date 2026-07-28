<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

final class StoreTaskAttachmentRequest extends FormRequest
{
    /**
     * MIME được phép đính kèm. Kiểm tra bằng MIME thật của tệp, không theo phần mở rộng.
     *
     * @var list<string>
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/csv',
        'text/plain',
        'application/zip',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('attach', $this->route('task'));
    }

    protected function prepareForValidation(): void
    {
        // Khi tổng dung lượng vượt post_max_size của PHP, body bị bỏ trắng trước khi
        // tới Laravel. Không xử lý thì người dùng nhận lỗi "files là bắt buộc" gây hiểu nhầm.
        $contentLength = (int) $this->server('CONTENT_LENGTH', 0);

        if ($contentLength > 0 && $this->all() === [] && $this->allFiles() === []) {
            throw ValidationException::withMessages([
                'files' => 'Tổng dung lượng vượt quá giới hạn máy chủ cho phép. Vui lòng tải ít tệp hơn trong một lần.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => [
                'required',
                'file',
                'max:10240',
                'mimetypes:'.implode(',', self::ALLOWED_MIME_TYPES),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Vui lòng chọn ít nhất một tệp.',
            'files.max' => 'Mỗi lần chỉ tải lên tối đa 5 tệp.',
            'files.*.max' => 'Mỗi tệp không được vượt quá 10MB.',
            'files.*.mimetypes' => 'Định dạng tệp không được phép đính kèm.',
        ];
    }
}
