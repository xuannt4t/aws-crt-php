<?php

namespace App\Http\Requests;

use App\Enums\ProjectMemberRole;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProjectMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        return $this->user()->can('manageMembers', $project);
    }

    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('is_active', true)),
                Rule::unique('project_members', 'user_id')->where('project_id', $project->id),
            ],
            'role' => ['required', Rule::enum(ProjectMemberRole::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Vui lòng chọn người dùng.',
            'user_id.integer' => 'Người dùng không hợp lệ.',
            'user_id.exists' => 'Người dùng không tồn tại hoặc đã bị vô hiệu hoá.',
            'user_id.unique' => 'Người dùng này đã là thành viên của dự án.',
            'role.required' => 'Vui lòng chọn vai trò.',
            'role.enum' => 'Vai trò không hợp lệ.',
        ];
    }
}
