<?php

namespace App\Http\Controllers;

use App\Actions\Project\AddProjectMemberAction;
use App\Actions\Project\RemoveProjectMemberAction;
use App\Actions\Project\UpdateProjectMemberRoleAction;
use App\Http\Requests\StoreProjectMemberRequest;
use App\Http\Requests\UpdateProjectMemberRequest;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

final class ProjectMemberController extends Controller
{
    public function store(
        StoreProjectMemberRequest $request,
        Project $project,
        AddProjectMemberAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $project, $request->validated());

        return Redirect::back()->with('success', 'Đã thêm thành viên vào dự án.');
    }

    public function update(
        UpdateProjectMemberRequest $request,
        Project $project,
        ProjectMember $member,
        UpdateProjectMemberRoleAction $action,
    ): RedirectResponse {
        $action->execute($request->user(), $project, $member, $request->validated()['role']);

        return Redirect::back()->with('success', 'Đã cập nhật vai trò thành viên.');
    }

    public function destroy(
        Project $project,
        ProjectMember $member,
        RemoveProjectMemberAction $action,
    ): RedirectResponse {
        $this->authorize('manageMembers', $project);

        $action->execute(request()->user(), $project, $member);

        return Redirect::back()->with('success', 'Đã xoá thành viên khỏi dự án.');
    }
}
