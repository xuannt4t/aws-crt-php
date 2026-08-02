<?php

namespace App\Http\Requests;

use App\Enums\PermissionName;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'organization_unit_id' => [
                'required',
                'integer',
                $this->onlyWithProjectUpdatePermission($project->organization_unit_id),
                Rule::exists('organization_units', 'id')->whereNull('deleted_at'),
            ],
            'owner_id' => [
                'required',
                'integer',
                $this->onlyWithProjectUpdatePermission($project->owner_id),
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_\-]+$/',
                Rule::unique('projects', 'code')->ignore($this->route('project')),
            ],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => [
                'nullable',
                Rule::enum(ProjectStatus::class),
                $this->statusStaysOutOfTheCloseWorkflow($project),
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Mã dự án chỉ được chứa chữ hoa, số, dấu gạch dưới và gạch ngang.',
            'code.unique' => 'Mã dự án đã tồn tại.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ];
    }

    /**
     * Việc đóng dự án chỉ được thực hiện qua projects.close (CloseProjectAction)
     * để luôn ghi closed_at, close_reason và nhật ký kiểm toán. Vì vậy màn hình
     * chỉnh sửa không được đặt trạng thái đã đóng, cũng không được mở lại dự án
     * đã đóng (sẽ để lại closed_at/close_reason cũ). Giữ nguyên trạng thái hiện
     * tại vẫn hợp lệ để form sửa các trường khác.
     */
    private function statusStaysOutOfTheCloseWorkflow(Project $project): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($project): void {
            if ($value === null || $value === $project->status?->value) {
                return;
            }

            if (in_array($value, Project::closedStatusValues(), true)) {
                $fail('Không thể đóng dự án ở màn hình chỉnh sửa. Vui lòng dùng chức năng "Đóng dự án".');

                return;
            }

            if ($project->isClosed()) {
                $fail('Không thể mở lại dự án đã đóng.');
            }
        };
    }

    /**
     * Chỉ người có quyền hệ thống project.update mới được đổi giá trị của
     * trường; quản lý dự án (không có quyền này) vẫn phải gửi lại giá trị hiện
     * tại — xem ProjectPolicy::update().
     */
    private function onlyWithProjectUpdatePermission(mixed $currentValue): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($currentValue): void {
            if ((int) $value === (int) $currentValue) {
                return;
            }

            if ($this->user()->can(PermissionName::ProjectUpdate->value)) {
                return;
            }

            $fail($attribute === 'owner_id'
                ? 'Bạn không có quyền thay đổi chủ dự án.'
                : 'Bạn không có quyền thay đổi đơn vị sở hữu dự án.');
        };
    }
}
