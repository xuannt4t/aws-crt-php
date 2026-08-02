<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('a user with project view permission can list filtered paginated projects', function () {
    $viewer = userWithPermissions([PermissionName::ProjectView->value]);
    $unit = OrganizationUnit::factory()->create();

    Project::factory()->count(21)->create([
        'organization_unit_id' => $unit->id,
        'status' => ProjectStatus::Active,
    ]);
    Project::factory()->create([
        'name' => 'Không khớp bộ lọc',
        'status' => ProjectStatus::Completed,
    ]);

    $response = $this->actingAs($viewer)->get(route('projects.index', [
        'status' => ProjectStatus::Active->value,
        'organization_unit_id' => $unit->id,
    ]), inertiaHeaders());

    $response->assertOk()
        ->assertJsonPath('component', 'Projects/Index')
        ->assertJsonCount(20, 'props.projects.data')
        ->assertJsonPath('props.projects.total', 21);
});

test('a user without project view permission cannot list projects', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertForbidden();
});

test('project list exposes progress task count open task count and member count without N plus 1', function () {
    $viewer = userWithPermissions([PermissionName::ProjectView->value]);
    $project = Project::factory()->create();
    ProjectMember::factory()->count(2)->create(['project_id' => $project->id]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Completed, 'progress' => 100]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'progress' => 0]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Cancelled, 'progress' => 0]);

    DB::enableQueryLog();

    $response = $this->actingAs($viewer)->get(route('projects.index'), inertiaHeaders());

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk()
        ->assertJsonPath('props.projects.data.0.progress', 50)
        ->assertJsonPath('props.projects.data.0.task_count', 3)
        ->assertJsonPath('props.projects.data.0.open_task_count', 1)
        ->assertJsonPath('props.projects.data.0.member_count', 2);

    expect($queryCount)->toBeLessThan(10);
});

test('only mine filter returns only projects the user is a member of', function () {
    $viewer = userWithPermissions([PermissionName::ProjectView->value]);
    $myProject = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $myProject->id, 'user_id' => $viewer->id]);
    Project::factory()->create();

    $this->actingAs($viewer)
        ->get(route('projects.index', ['only_mine' => true]), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.projects.data')
        ->assertJsonPath('props.projects.data.0.id', $myProject->id);
});

test('a user with create permission creates a project and becomes manager', function () {
    $creator = userWithPermissions([PermissionName::ProjectCreate->value]);
    $unit = OrganizationUnit::factory()->create();
    $owner = User::factory()->create();

    $response = $this->actingAs($creator)->post(route('projects.store'), [
        'organization_unit_id' => $unit->id,
        'owner_id' => $owner->id,
        'code' => 'PRJ-001',
        'name' => 'Dự án thí điểm',
        'description' => 'Mô tả dự án',
    ]);

    $response->assertRedirect(route('projects.index'));

    $project = Project::where('code', 'PRJ-001')->firstOrFail();

    expect($project->status)->toBe(ProjectStatus::Planning)
        ->and($project->owner_id)->toBe($owner->id);

    $this->assertDatabaseHas('project_members', [
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'role' => ProjectMemberRole::Manager->value,
    ]);
});

test('a user without project create permission cannot create a project', function () {
    $user = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($user)->post(route('projects.store'), [
        'organization_unit_id' => $unit->id,
        'owner_id' => $user->id,
        'code' => 'PRJ-002',
        'name' => 'Dự án trái phép',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('projects', ['code' => 'PRJ-002']);
});

test('project code must be unique', function () {
    $creator = userWithPermissions([PermissionName::ProjectCreate->value]);
    $unit = OrganizationUnit::factory()->create();
    Project::factory()->create(['code' => 'PRJ-DUP']);

    $this->actingAs($creator)->post(route('projects.store'), [
        'organization_unit_id' => $unit->id,
        'owner_id' => $creator->id,
        'code' => 'PRJ-DUP',
        'name' => 'Dự án trùng mã',
    ])->assertSessionHasErrors('code');
});

test('project code must follow the expected format', function () {
    $creator = userWithPermissions([PermissionName::ProjectCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)->post(route('projects.store'), [
        'organization_unit_id' => $unit->id,
        'owner_id' => $creator->id,
        'code' => 'prj lowercase invalid',
        'name' => 'Dự án mã sai',
    ])->assertSessionHasErrors('code');
});

test('project end date cannot be before start date', function () {
    $creator = userWithPermissions([PermissionName::ProjectCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)->post(route('projects.store'), [
        'organization_unit_id' => $unit->id,
        'owner_id' => $creator->id,
        'code' => 'PRJ-003',
        'name' => 'Dự án ngày sai',
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-01',
    ])->assertSessionHasErrors('end_date');
});

test('a user with update permission can change the project owner and the new owner becomes manager', function () {
    $updater = userWithPermissions([PermissionName::ProjectUpdate->value]);
    $project = Project::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $project->owner_id,
        'role' => ProjectMemberRole::Manager,
    ]);
    $newOwner = User::factory()->create();

    $response = $this->actingAs($updater)->put(route('projects.update', $project), [
        'organization_unit_id' => $project->organization_unit_id,
        'owner_id' => $newOwner->id,
        'code' => $project->code,
        'name' => 'Dự án đổi chủ',
    ]);

    $response->assertRedirect(route('projects.index'));

    expect($project->fresh()->owner_id)->toBe($newOwner->id);

    $this->assertDatabaseHas('project_members', [
        'project_id' => $project->id,
        'user_id' => $newOwner->id,
        'role' => ProjectMemberRole::Manager->value,
    ]);

    $this->assertDatabaseHas('project_members', [
        'project_id' => $project->id,
        'user_id' => $project->owner_id,
    ]);
});

test('a user with delete permission can soft delete a project and creates audit log', function () {
    $deleter = userWithPermissions([PermissionName::ProjectDelete->value]);
    $project = Project::factory()->create();

    $response = $this->actingAs($deleter)->delete(route('projects.destroy', $project));

    $response->assertRedirect(route('projects.index'));
    $this->assertSoftDeleted($project);

    $auditLog = AuditLog::where('action', AuditAction::ProjectDeleted->value)->firstOrFail();

    expect($auditLog->actor_id)->toBe($deleter->id)
        ->and($auditLog->subject_id)->toBe($project->id);
});

test('a user without delete permission cannot delete a project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->delete(route('projects.destroy', $project))
        ->assertForbidden();

    $this->assertNotSoftDeleted($project);
});

test('a user with view permission can view project details with progress and actions', function () {
    $viewer = userWithPermissions([
        PermissionName::ProjectView->value,
        PermissionName::ProjectUpdate->value,
    ]);
    $project = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $project->id]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Completed, 'progress' => 100]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'progress' => 0]);

    $this->actingAs($viewer)
        ->get(route('projects.show', $project), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'Projects/Show')
        ->assertJsonPath('props.project.id', $project->id)
        ->assertJsonPath('props.project.progress', 50)
        ->assertJsonPath('props.actions.update', true)
        ->assertJsonStructure(['props' => ['members', 'tasks']]);
});

test('a member without project view permission can still view the project', function () {
    $member = User::factory()->create();
    $project = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);

    $this->actingAs($member)
        ->get(route('projects.show', $project), inertiaHeaders())
        ->assertOk();
});

test('a user who is not a member and lacks project view permission cannot view the project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.show', $project), inertiaHeaders())
        ->assertForbidden();
});
