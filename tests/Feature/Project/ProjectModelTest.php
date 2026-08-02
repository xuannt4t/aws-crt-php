<?php

use App\Enums\ProjectMemberRole;
use App\Enums\TaskStatus;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;

test('a project belongs to its organization unit and owner and has members and tasks', function () {
    $unit = OrganizationUnit::factory()->create();
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'organization_unit_id' => $unit->id,
        'owner_id' => $owner->id,
    ]);
    $member = ProjectMember::factory()->create(['project_id' => $project->id]);
    $task = Task::factory()->create(['project_id' => $project->id]);

    expect($project->organizationUnit->is($unit))->toBeTrue()
        ->and($project->owner->is($owner))->toBeTrue()
        ->and($project->members->pluck('id')->all())->toBe([$member->id])
        ->and($project->tasks->pluck('id')->all())->toBe([$task->id]);
});

test('calculate progress returns zero when the project has no tasks', function () {
    $project = Project::factory()->create();

    expect($project->calculateProgress())->toBe(0);
});

test('calculate progress returns zero when the project only has cancelled tasks', function () {
    $project = Project::factory()->create();
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Cancelled,
        'progress' => 80,
    ]);

    expect($project->calculateProgress())->toBe(0);
});

test('calculate progress returns the rounded average of non cancelled task progress', function () {
    $project = Project::factory()->create();
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::InProgress,
        'progress' => 40,
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Completed,
        'progress' => 100,
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Cancelled,
        'progress' => 0,
    ]);

    // average of 40 and 100 = 70, cancelled task ignored
    expect($project->calculateProgress())->toBe(70);
});

test('open tasks scope excludes completed and cancelled tasks', function () {
    $project = Project::factory()->create();
    $open = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::InProgress,
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Completed,
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Cancelled,
    ]);

    expect($project->openTasks->pluck('id')->all())->toBe([$open->id]);
});

test('is manager and is member reflect the member role in project members', function () {
    $project = Project::factory()->create();
    $manager = User::factory()->create();
    $member = User::factory()->create();
    $stranger = User::factory()->create();

    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'role' => ProjectMemberRole::Manager,
    ]);
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $member->id,
        'role' => ProjectMemberRole::Member,
    ]);

    expect($project->isManager($manager))->toBeTrue()
        ->and($project->isMember($manager))->toBeTrue()
        ->and($project->isManager($member))->toBeFalse()
        ->and($project->isMember($member))->toBeTrue()
        ->and($project->isMember($stranger))->toBeFalse()
        ->and($project->isManager($stranger))->toBeFalse();
});

test('owner remains accessible through the owner relation after being soft deleted', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $owner->delete();

    expect($project->fresh()->owner->is($owner))->toBeTrue();
});
