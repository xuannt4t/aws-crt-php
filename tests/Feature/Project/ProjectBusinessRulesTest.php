<?php

use App\Actions\Project\CreateProjectAction;
use App\Actions\Project\DeleteProjectAction;
use App\Actions\Project\UpdateProjectAction;
use App\Enums\AuditAction;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Models\AuditLog;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

test('creating a project without a status defaults to planning and adds the owner as manager', function () {
    $actor = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();
    $owner = User::factory()->create();

    $project = app(CreateProjectAction::class)->execute($actor, [
        'organization_unit_id' => $unit->id,
        'owner_id' => $owner->id,
        'code' => 'PRJ-100',
        'name' => 'Dự án mặc định',
    ]);

    expect($project->status)->toBe(ProjectStatus::Planning);

    $membership = ProjectMember::where('project_id', $project->id)
        ->where('user_id', $owner->id)
        ->firstOrFail();

    expect($membership->role)->toBe(ProjectMemberRole::Manager)
        ->and($membership->joined_at)->not->toBeNull();
});

test('updating the owner promotes the new owner to manager while keeping the old owner membership', function () {
    $actor = User::factory()->create();
    $project = Project::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $project->owner_id,
        'role' => ProjectMemberRole::Manager,
    ]);
    $newOwner = User::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $newOwner->id,
        'role' => ProjectMemberRole::Viewer,
    ]);

    $updated = app(UpdateProjectAction::class)->execute($actor, $project, [
        'organization_unit_id' => $project->organization_unit_id,
        'owner_id' => $newOwner->id,
        'code' => $project->code,
        'name' => $project->name,
    ]);

    expect($updated->owner_id)->toBe($newOwner->id);

    $newOwnerMembership = ProjectMember::where('project_id', $project->id)
        ->where('user_id', $newOwner->id)
        ->firstOrFail();
    expect($newOwnerMembership->role)->toBe(ProjectMemberRole::Manager);

    $oldOwnerMembership = ProjectMember::where('project_id', $project->id)
        ->where('user_id', $project->owner_id)
        ->firstOrFail();
    expect($oldOwnerMembership->role)->toBe(ProjectMemberRole::Manager);
});

test('updating the owner to a user without a membership creates a manager membership for them', function () {
    $actor = User::factory()->create();
    $project = Project::factory()->create();
    $newOwner = User::factory()->create();

    app(UpdateProjectAction::class)->execute($actor, $project, [
        'organization_unit_id' => $project->organization_unit_id,
        'owner_id' => $newOwner->id,
        'code' => $project->code,
        'name' => $project->name,
    ]);

    $this->assertDatabaseHas('project_members', [
        'project_id' => $project->id,
        'user_id' => $newOwner->id,
        'role' => ProjectMemberRole::Manager->value,
    ]);
});

test('deleting a project soft deletes it and records an audit log with expected metadata', function () {
    $actor = User::factory()->create();
    $project = Project::factory()->create();

    app(DeleteProjectAction::class)->execute($actor, $project);

    expect($project->fresh()->trashed())->toBeTrue();

    $auditLog = AuditLog::where('action', AuditAction::ProjectDeleted->value)
        ->where('subject_id', $project->id)
        ->firstOrFail();

    expect($auditLog->before_values)->toMatchArray([
        'organization_unit_id' => $project->organization_unit_id,
        'owner_id' => $project->owner_id,
        'code' => $project->code,
        'name' => $project->name,
        'status' => $project->status->value,
    ])->and($auditLog->metadata)->toHaveKey('task_count');
});
