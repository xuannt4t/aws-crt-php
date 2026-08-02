<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;

test('closing a project with no open tasks marks it completed and records a plain closed audit log', function () {
    $actor = userWithPermissions([PermissionName::ProjectClose->value]);
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Completed]);

    $response = $this->actingAs($actor)->patch(route('projects.close', $project));

    $response->assertRedirect()->assertSessionHas('success', 'Đã đóng dự án.');

    $project->refresh();
    expect($project->status)->toBe(ProjectStatus::Completed)
        ->and($project->closed_at)->not->toBeNull()
        ->and($project->close_reason)->toBeNull();

    $auditLog = AuditLog::where('action', AuditAction::ProjectClosed->value)
        ->where('subject_id', $project->id)
        ->firstOrFail();

    expect($auditLog->actor_id)->toBe($actor->id);
});

test('closing a project with open tasks and no reason fails validation and leaves the project unchanged', function () {
    $actor = userWithPermissions([PermissionName::ProjectClose->value]);
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::InProgress]);

    $response = $this->actingAs($actor)->patch(route('projects.close', $project));

    $response->assertSessionHasErrors('close_reason');

    $project->refresh();
    expect($project->status)->toBe(ProjectStatus::Active)
        ->and($project->closed_at)->toBeNull();

    expect(AuditLog::where('subject_id', $project->id)->exists())->toBeFalse();
});

test('closing a project with open tasks and a valid reason closes it with an exception audit log', function () {
    $actor = userWithPermissions([PermissionName::ProjectClose->value]);
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    $openTask = Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::InProgress]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Completed]);

    $response = $this->actingAs($actor)->patch(route('projects.close', $project), [
        'close_reason' => 'Khách hàng yêu cầu kết thúc sớm dự án này.',
    ]);

    $response->assertRedirect()->assertSessionHas('success', 'Đã đóng dự án.');

    $project->refresh();
    expect($project->status)->toBe(ProjectStatus::Completed)
        ->and($project->closed_at)->not->toBeNull()
        ->and($project->close_reason)->toBe('Khách hàng yêu cầu kết thúc sớm dự án này.');

    $auditLog = AuditLog::where('action', AuditAction::ProjectClosedWithException->value)
        ->where('subject_id', $project->id)
        ->firstOrFail();

    expect($auditLog->metadata['open_task_count'])->toBe(1)
        ->and($auditLog->metadata['open_task_ids'])->toBe([$openTask->id]);
});

test('closing an already closed project fails validation with a status error', function () {
    $actor = userWithPermissions([PermissionName::ProjectClose->value]);
    $project = Project::factory()->create([
        'status' => ProjectStatus::Completed,
        'closed_at' => now(),
    ]);

    $response = $this->actingAs($actor)->patch(route('projects.close', $project));

    $response->assertSessionHasErrors('status');
});

test('a user without close permission and not a manager cannot close a project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);

    $this->actingAs($user)
        ->patch(route('projects.close', $project))
        ->assertForbidden();

    expect($project->fresh()->status)->toBe(ProjectStatus::Active);
});

test('a project manager without the close permission can still close the project', function () {
    $manager = User::factory()->create();
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'role' => ProjectMemberRole::Manager,
    ]);

    $response = $this->actingAs($manager)->patch(route('projects.close', $project));

    $response->assertRedirect()->assertSessionHas('success');

    expect($project->fresh()->status)->toBe(ProjectStatus::Completed);
});
