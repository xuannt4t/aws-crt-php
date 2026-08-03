<?php

use App\Enums\PermissionName;
use App\Enums\ProjectMemberRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

test('viewAny is open to every authenticated user while create requires permission', function () {
    $viewer = userWithPermissions([PermissionName::ProjectView->value]);
    $creator = userWithPermissions([PermissionName::ProjectCreate->value]);
    $userWithoutPermission = User::factory()->create();

    // Danh sách dự án mở cho mọi người dùng; nội dung được giới hạn bằng
    // Project::scopeVisibleTo(), không bằng viewAny.
    expect($viewer->can('viewAny', Project::class))->toBeTrue()
        ->and($userWithoutPermission->can('viewAny', Project::class))->toBeTrue()
        ->and($creator->can('create', Project::class))->toBeTrue()
        ->and($userWithoutPermission->can('create', Project::class))->toBeFalse();
});

test('view requires the project view gate, then the scope: membership satisfies own, view_all reaches every project', function () {
    // Hai lớp tách bạch (spec §3.1): project.view mở cổng vào module,
    // project.view_own/department/all quyết định thấy bản ghi nào. Thành viên
    // dự án tự nhiên nằm trong phạm vi "own", nhưng vẫn cần cổng project.view
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

test('update is allowed by permission or project manager role', function () {
    $project = Project::factory()->create();

    $permittedNonMember = userWithPermissions([PermissionName::ProjectUpdate->value]);
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
        ->and($manager->can('update', $project))->toBeTrue()
        ->and($memberWithoutPermission->can('update', $project))->toBeFalse()
        ->and($viewerWithoutPermission->can('update', $project))->toBeFalse()
        ->and($outsider->can('update', $project))->toBeFalse();
});

test('delete requires permission only, regardless of project role', function () {
    $project = Project::factory()->create();

    $deleter = userWithPermissions([PermissionName::ProjectDelete->value]);
    $manager = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'role' => ProjectMemberRole::Manager,
    ]);
    $outsider = User::factory()->create();

    expect($deleter->can('delete', $project))->toBeTrue()
        ->and($manager->can('delete', $project))->toBeFalse()
        ->and($outsider->can('delete', $project))->toBeFalse();
});

test('manageMembers is allowed by permission or project manager role', function () {
    $project = Project::factory()->create();

    $permittedNonMember = userWithPermissions([PermissionName::ProjectManageMembers->value]);
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
        ->and($manager->can('manageMembers', $project))->toBeTrue()
        ->and($memberWithoutPermission->can('manageMembers', $project))->toBeFalse()
        ->and($viewerWithoutPermission->can('manageMembers', $project))->toBeFalse()
        ->and($outsider->can('manageMembers', $project))->toBeFalse();
});

test('close is allowed by permission or project manager role', function () {
    $project = Project::factory()->create();

    $permittedNonMember = userWithPermissions([PermissionName::ProjectClose->value]);
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
        ->and($manager->can('close', $project))->toBeTrue()
        ->and($memberWithoutPermission->can('close', $project))->toBeFalse()
        ->and($viewerWithoutPermission->can('close', $project))->toBeFalse()
        ->and($outsider->can('close', $project))->toBeFalse();
});
