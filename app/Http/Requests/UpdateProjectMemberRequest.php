<?php

namespace App\Http\Requests;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectTaskVisibility;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $this->user()->can('manageMembers', $project);
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
            'task_visibility' => ['required', Rule::enum(ProjectTaskVisibility::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Vui lòng chọn vai trò.',
            'role.enum' => 'Vai trò không hợp lệ.',
            'task_visibility.required' => 'Vui lòng chọn quyền xem việc.',
            'task_visibility.enum' => 'Quyền xem việc không hợp lệ.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('role')) {
                return;
            }

            /** @var Project $project */
            $project = $this->route('project');
            /** @var ProjectMember $member */
            $member = $this->route('member');

            $newRole = $this->input('role');

            if ($member->user_id === $project->owner_id
                && $newRole !== ProjectMemberRole::Manager->value
            ) {
                $validator->errors()->add(
                    'role',
                    'Không thể hạ vai trò của chủ việc dự án xuống dưới Quản lý việc dự án.',
                );
            }
        });
    }
}
