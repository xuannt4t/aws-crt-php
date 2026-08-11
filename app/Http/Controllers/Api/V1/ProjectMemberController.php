<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Project\AddProjectMemberAction;
use App\Actions\Project\RemoveProjectMemberAction;
use App\Actions\Project\UpdateProjectMemberAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectMemberRequest;
use App\Http\Requests\UpdateProjectMemberRequest;
use App\Http\Resources\Api\V1\ProjectMemberResource;
use App\Http\Responses\ApiResponse;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProjectMemberController extends Controller
{
    public function store(StoreProjectMemberRequest $request, Project $project, AddProjectMemberAction $action): JsonResponse
    {
        $member = $action->execute($request->user(), $project, $request->validated());
        $member->load('user:id,name,email,avatar_path');

        return ApiResponse::success(ProjectMemberResource::make($member)->resolve($request), 'Đã thêm thành viên.', 201);
    }

    public function update(
        UpdateProjectMemberRequest $request,
        Project $project,
        ProjectMember $member,
        UpdateProjectMemberAction $action,
    ): JsonResponse {
        $data = $request->validated();
        $member = $action->execute($request->user(), $project, $member, $data['role'], $data['task_visibility']);
        $member->load('user:id,name,email,avatar_path');

        return ApiResponse::success(ProjectMemberResource::make($member)->resolve($request), 'Đã cập nhật thành viên.');
    }

    public function destroy(
        Request $request,
        Project $project,
        ProjectMember $member,
        RemoveProjectMemberAction $action,
    ): JsonResponse {
        $this->authorize('manageMembers', $project);
        $action->execute($request->user(), $project, $member);

        return ApiResponse::success(null, 'Đã xóa thành viên.');
    }
}
