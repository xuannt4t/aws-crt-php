<?php

namespace App\Http\Requests;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskStatusBucket;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Task::class);
    }

    /**
     * Query string không có kiểu boolean: tuỳ thư viện phía client mà `true`
     * thành "true", "1" hay "on". Luật `boolean` của Laravel chỉ chấp nhận
     * 1/0/"1"/"0", nên "true" sẽ trượt validation và cả request bị chặn — người
     * dùng bấm ô "Trễ hạn" thấy trang không phản ứng gì, không có báo lỗi nào.
     * Chuẩn hoá ở đây một lần để endpoint không phụ thuộc vào cách client gửi.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('overdue')) {
            return;
        }

        $this->merge([
            'overdue' => filter_var($this->input('overdue'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            // Ô tóm tắt bấm được: lọc theo nhóm trạng thái của ô, không phải một
            // trạng thái đơn lẻ — ô "Chưa làm" gộp nháp và cần làm.
            'bucket' => ['nullable', Rule::enum(TaskStatusBucket::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'organization_unit_id' => ['nullable', 'integer', Rule::exists('organization_units', 'id')->whereNull('deleted_at')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'assignee_ids' => ['nullable', 'array', 'max:50'],
            'assignee_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'overdue' => ['nullable', 'boolean'],
        ];
    }
}
