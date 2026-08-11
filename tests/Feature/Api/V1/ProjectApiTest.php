<?php

use App\Enums\PermissionName;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTaskVisibility;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function apiProjectUser(array $extraPermissions = []): User
{
    return userWithPermissions(array_values(array_unique([
        PermissionName::ProjectView->value,
        PermissionName::ProjectViewAll->value,
        ...$extraPermissions,
    ])));
}

test('project api exposes filtered visible projects with progress counts', function () {
    $user = apiProjectUser();
    Project::factory()->create(['name' => 'Ứng dụng mobile', 'code' => 'MOBILE']);
    Project::factory()->create(['name' => 'Website', 'code' => 'WEB']);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/projects?search=MOBILE')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'MOBILE')
        ->assertJsonStructure(['data' => [['progress', 'task_count', 'open_task_count', 'member_count']]]);
});

test('project api supports crud close and member management', function () {
    $unit = OrganizationUnit::factory()->create();
    $owner = apiProjectUser([
        PermissionName::ProjectCreate->value,
        PermissionName::ProjectUpdate->value,
        PermissionName::ProjectDelete->value,
        PermissionName::ProjectManageMembers->value,
        PermissionName::ProjectClose->value,
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
    ]);
    $memberUser = User::factory()->create();
    Sanctum::actingAs($owner);

    $created = $this->postJson('/api/v1/projects', [
        'organization_unit_id' => $unit->id,
        'owner_id' => $owner->id,
        'code' => 'APP_01',
        'name' => 'Việc dự án app',
        'description' => 'Mô tả',
        'status' => ProjectStatus::Planning->value,
    ])->assertCreated();
    $id = $created->json('data.id');

    $added = $this->postJson("/api/v1/projects/{$id}/members", [
        'user_id' => $memberUser->id,
        'role' => ProjectMemberRole::Member->value,
        'task_visibility' => ProjectTaskVisibility::Own->value,
    ])->assertCreated();
    $memberId = $added->json('data.id');

    $this->patchJson("/api/v1/projects/{$id}/members/{$memberId}", [
        'role' => ProjectMemberRole::Viewer->value,
        'task_visibility' => ProjectTaskVisibility::All->value,
    ])->assertOk()->assertJsonPath('data.role', 'viewer');

    $this->getJson("/api/v1/projects/{$id}")
        ->assertOk()
        ->assertJsonPath('data.project.id', $id)
        ->assertJsonFragment(['user_id' => $memberUser->id])
        ->assertJsonStructure(['data' => ['project', 'members', 'tasks']]);

    $this->deleteJson("/api/v1/projects/{$id}/members/{$memberId}")->assertOk();

    $this->patchJson("/api/v1/projects/{$id}", [
        'organization_unit_id' => $unit->id,
        'owner_id' => $owner->id,
        'code' => 'APP_01',
        'name' => 'Việc dự án app mới',
        'description' => 'Mô tả mới',
        'status' => ProjectStatus::Active->value,
    ])->assertOk()->assertJsonPath('data.name', 'Việc dự án app mới');

    $this->patchJson("/api/v1/projects/{$id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', ProjectStatus::Completed->value);

    $deletable = Project::factory()->create(['owner_id' => $owner->id]);
    ProjectMember::factory()->create([
        'project_id' => $deletable->id,
        'user_id' => $owner->id,
        'role' => ProjectMemberRole::Manager,
    ]);
    $this->deleteJson("/api/v1/projects/{$deletable->id}")->assertOk();
    $this->assertSoftDeleted('projects', ['id' => $deletable->id]);
});

test('project api enforces permissions and scoped member binding', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $member = ProjectMember::factory()->create(['project_id' => $otherProject->id]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/projects')->assertForbidden();
    $this->getJson("/api/v1/projects/{$project->id}")->assertForbidden();
    $this->patchJson("/api/v1/projects/{$project->id}/members/{$member->id}", [
        'role' => ProjectMemberRole::Member->value,
        'task_visibility' => ProjectTaskVisibility::Own->value,
    ])->assertNotFound();
});
