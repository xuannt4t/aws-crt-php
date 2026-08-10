<?php

use App\Enums\PermissionName;
use App\Enums\ProjectMemberRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

test('viewAny requires the project view gate while create requires its own permission', function () {
    $viewer = userWithPermissions([PermissionName::ProjectView->value]);
    $creator = userWithPermissions([PermissionName::ProjectCreate->value]);
    $userWithoutPermission = User::factory()->create();

    // Mô hình hai lớp: project.view là cổng module (viewAny), project.view_*
    // là phạm vi bản ghi. viewAny và view() phải cùng đòi cổng này, nếu không
    // người dùng thấy việc dự án trong danh sách rồi nhận 403 khi bấm vào.
    expect($viewer->can('viewAny', Project::class))->toBeTrue()
        ->and($userWithoutPermission->can('viewAny', Project::class))->toBeFalse()
        ->and($creator->can('create', Project::class))->toBeTrue()
        ->and($userWithoutPermission->can('create', Project::class))->toBeFalse();
});

test('a project member without the project view gate is excluded from the list and from show alike', function () {
    // Danh sách và policy phải đồng ý: thành viên thiếu project.view không
    // được thấy việc dự án trong danh sách, cũng không mở được trang chi tiết.
    $member = User::factory()->create();
    $project = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);

    expect($member->can('viewAny', Project::class))->toBeFalse()
        ->and($member->can('view', $project))->toBeFalse();

    $this->actingAs($member)
        ->get(route('projects.index'))
        ->assertForbidden();

    $this->actingAs($member)
        ->get(route('projects.show', $project))
        ->assertForbidden();
});

test('view requires the project view gate, then the scope: membership satisfies own, view_all reaches every project', function () {
    // Hai lớp tách bạch (spec §3.1): project.view mở cổng vào module,
    // project.view_own/department/all quyết định thấy bản ghi nào. Thành viên
    // việc dự án tự nhiên nằm trong phạm vi "own", nhưng vẫn cần cổng project.view
    // — thành viên không có quyền này bị chặn ở cổng, đúng như thiết kế hai lớp.
    $project = Project::factory()->create();

    $nonMemberWithViewAll = userWithPermissions([
        PermissionName::ProjectView->value,
        PermissionName::ProjectViewAll->value,
    ]);

    $manager = userWithPermissions([PermissionName::ProjectView->value]);
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'role' => ProjectMemberRole::Manager,
    ]);

    $member = userWithPermissions([PermissionName::ProjectView->value]);
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $member->id,
        'role' => ProjectMemberRole::Member,
    ]);

    $viewerRoleUser = userWithPermissions([PermissionName::ProjectView->value]);
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $viewerRoleUser->id,
        'role' => ProjectMemberRole::Viewer,
    ]);

    $memberWithoutGate = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $memberWithoutGate->id,
        'role' => ProjectMemberRole::Member,
    ]);

    $outsider = User::factory()->create();

    expect($nonMemberWithViewAll->can('view', $project))->toBeTrue()
        ->and($manager->can('view', $project))->toBeTrue()
        ->and($member->can('view', $project))->toBeTrue()
        ->and($viewerRoleUser->can('view', $project))->toBeTrue()
        ->and($memberWithoutGate->can('view', $project))->toBeFalse()
        ->and($outsider->can('view', $project))->toBeFalse();
});

test('update is allowed by permission plus scope, or by the project manager role', function () {
    $project = Project::factory()->create();

    // Nhánh "có quyền" nay còn phải nằm trong phạm vi dữ liệu; nhánh "là quản
    // lý việc dự án" giữ nguyên vì quản lý luôn là thành viên nên đã ở trong own.
    $permittedNonMember = userWithPermissions([
        PermissionName::ProjectUpdate->value,
        PermissionName::ProjectViewAll->value,
    ]);
    $permittedOutOfScope = userWithPermissions([PermissionName::ProjectUpdate->value]);
    $manager = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'role' => ProjectMemberRole::Manager,
    ]);
    $memberWithoutPermission = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $memberWithoutPermission->id,
        'role' => ProjectMemberRole::Member,
    ]);
    $viewerWithoutPermission = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $viewerWithoutPermission->id,
        'role' => ProjectMemberRole::Viewer,
    ]);
    $outsider = User::factory()->create();

    expect($permittedNonMember->can('update', $project))->toBeTrue()
        ->and($permittedOutOfScope->can('update', $project))->toBeFalse()
        ->and($manager->can('update', $project))->toBeTrue()
        ->and($memberWithoutPermission->can('update', $project))->toBeFalse()
        ->and($viewerWithoutPermission->can('update', $project))->toBeFalse()
        ->and($outsider->can('update', $project))->toBeFalse();
});

test('delete requires permission plus scope, and the project manager role never grants it', function () {
    $project = Project::factory()->create();

    $deleter = userWithPermissions([
        PermissionName::ProjectDelete->value,
        PermissionName::ProjectViewAll->value,
    ]);
    $deleterOutOfScope = userWithPermissions([PermissionName::ProjectDelete->value]);
    $manager = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'role' => ProjectMemberRole::Manager,
    ]);
    $outsider = User::factory()->create();

    expect($deleter->can('delete', $project))->toBeTrue()
        ->and($deleterOutOfScope->can('delete', $project))->toBeFalse()
        ->and($manager->can('delete', $project))->toBeFalse()
        ->and($outsider->can('delete', $project))->toBeFalse();
});

test('manageMembers is allowed by permission plus scope, or by the project manager role', function () {
    $project = Project::factory()->create();

    $permittedNonMember = userWithPermissions([
        PermissionName::ProjectManageMembers->value,
        PermissionName::ProjectViewAll->value,
    ]);
    $permittedOutOfScope = userWithPermissions([PermissionName::ProjectManageMembers->value]);
    $manager = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'role' => ProjectMemberRole::Manager,
    ]);
    $memberWithoutPermission = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $memberWithoutPermission->id,
        'role' => ProjectMemberRole::Member,
    ]);
    $viewerWithoutPermission = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $viewerWithoutPermission->id,
        'role' => ProjectMemberRole::Viewer,
    ]);
    $outsider = User::factory()->create();

    expect($permittedNonMember->can('manageMembers', $project))->toBeTrue()
        ->and($permittedOutOfScope->can('manageMembers', $project))->toBeFalse()
        ->and($manager->can('manageMembers', $project))->toBeTrue()
        ->and($memberWithoutPermission->can('manageMembers', $project))->toBeFalse()
        ->and($viewerWithoutPermission->can('manageMembers', $project))->toBeFalse()
        ->and($outsider->can('manageMembers', $project))->toBeFalse();
});

test('close is allowed by permission plus scope, or by the project manager role', function () {
    $project = Project::factory()->create();

    $permittedNonMember = userWithPermissions([
        PermissionName::ProjectClose->value,
        PermissionName::ProjectViewAll->value,
    ]);
    $permittedOutOfScope = userWithPermissions([PermissionName::ProjectClose->value]);
    $manager = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'role' => ProjectMemberRole::Manager,
    ]);
    $memberWithoutPermission = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $memberWithoutPermission->id,
        'role' => ProjectMemberRole::Member,
    ]);
    $viewerWithoutPermission = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $viewerWithoutPermission->id,
        'role' => ProjectMemberRole::Viewer,
    ]);
    $outsider = User::factory()->create();

    expect($permittedNonMember->can('close', $project))->toBeTrue()
        ->and($permittedOutOfScope->can('close', $project))->toBeFalse()
        ->and($manager->can('close', $project))->toBeTrue()
        ->and($memberWithoutPermission->can('close', $project))->toBeFalse()
        ->and($viewerWithoutPermission->can('close', $project))->toBeFalse()
        ->and($outsider->can('close', $project))->toBeFalse();
});
