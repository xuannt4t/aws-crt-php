<?php

use App\Enums\PermissionName;
use App\Enums\ProjectMemberRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

test('viewAny and create require permission only', function () {
    $viewer = userWithPermissions([PermissionName::ProjectView->value]);
    $creator = userWithPermissions([PermissionName::ProjectCreate->value]);
    $userWithoutPermission = User::factory()->create();

    expect($viewer->can('viewAny', Project::class))->toBeTrue()
        ->and($creator->can('create', Project::class))->toBeTrue()
        ->and($userWithoutPermission->can('viewAny', Project::class))->toBeFalse()
        ->and($userWithoutPermission->can('create', Project::class))->toBeFalse();
});

test('view is allowed by permission or any project membership', function () {
    $project = Project::factory()->create();

    $permittedNonMember = userWithPermissions([PermissionName::ProjectView->value]);
    $manager = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'role' => ProjectMemberRole::Manager,
    ]);
    $member = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $member->id,
        'role' => ProjectMemberRole::Member,
    ]);
    $viewerRoleUser = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $viewerRoleUser->id,
        'role' => ProjectMemberRole::Viewer,
    ]);
    $outsider = User::factory()->create();

    expect($permittedNonMember->can('view', $project))->toBeTrue()
        ->and($manager->can('view', $project))->toBeTrue()
        ->and($member->can('view', $project))->toBeTrue()
        ->and($viewerRoleUser->can('view', $project))->toBeTrue()
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
