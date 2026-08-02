<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\ProjectMemberRole;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

test('a user with manage members permission can add a member and creates audit log', function () {
    $actor = userWithPermissions([PermissionName::ProjectManageMembers->value]);
    $project = Project::factory()->create();
    $newMember = User::factory()->create(['is_active' => true]);

    $response = $this->actingAs($actor)->post(route('projects.members.store', $project), [
        'user_id' => $newMember->id,
        'role' => ProjectMemberRole::Member->value,
    ]);

    $response->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('project_members', [
        'project_id' => $project->id,
        'user_id' => $newMember->id,
        'role' => ProjectMemberRole::Member->value,
    ]);

    $auditLog = AuditLog::where('action', AuditAction::ProjectMemberAdded->value)->firstOrFail();

    expect($auditLog->actor_id)->toBe($actor->id)
        ->and($auditLog->subject_id)->toBe($project->id)
        ->and($auditLog->metadata['member_user_id'])->toBe($newMember->id)
        ->and($auditLog->metadata['role'])->toBe(ProjectMemberRole::Member->value);
});

test('a project manager without the manage members permission can still add a member', function () {
    $manager = User::factory()->create();
    $project = Project::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'role' => ProjectMemberRole::Manager,
    ]);
    $newMember = User::factory()->create(['is_active' => true]);

    $this->actingAs($manager)
        ->post(route('projects.members.store', $project), [
            'user_id' => $newMember->id,
            'role' => ProjectMemberRole::Member->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('project_members', [
        'project_id' => $project->id,
        'user_id' => $newMember->id,
    ]);
});

test('an outsider without permission cannot add a member', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $newMember = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->post(route('projects.members.store', $project), [
            'user_id' => $newMember->id,
            'role' => ProjectMemberRole::Member->value,
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('project_members', [
        'project_id' => $project->id,
        'user_id' => $newMember->id,
    ]);
});

test('adding the same user twice fails validation', function () {
    $actor = userWithPermissions([PermissionName::ProjectManageMembers->value]);
    $project = Project::factory()->create();
    $existingMember = ProjectMember::factory()->create(['project_id' => $project->id]);

    $this->actingAs($actor)
        ->post(route('projects.members.store', $project), [
            'user_id' => $existingMember->user_id,
            'role' => ProjectMemberRole::Member->value,
        ])
        ->assertSessionHasErrors('user_id');
});

test('adding a member requires an active user and a valid role', function () {
    $actor = userWithPermissions([PermissionName::ProjectManageMembers->value]);
    $project = Project::factory()->create();
    $inactiveUser = User::factory()->create(['is_active' => false]);

    $this->actingAs($actor)
        ->post(route('projects.members.store', $project), [
            'user_id' => $inactiveUser->id,
            'role' => ProjectMemberRole::Member->value,
        ])
        ->assertSessionHasErrors('user_id');

    $anotherUser = User::factory()->create(['is_active' => true]);

    $this->actingAs($actor)
        ->post(route('projects.members.store', $project), [
            'user_id' => $anotherUser->id,
            'role' => 'not-a-role',
        ])
        ->assertSessionHasErrors('role');
});

test('a user with manage members permission can update a member role and creates audit log', function () {
    $actor = userWithPermissions([PermissionName::ProjectManageMembers->value]);
    $project = Project::factory()->create();
    $member = ProjectMember::factory()->create([
        'project_id' => $project->id,
        'role' => ProjectMemberRole::Member,
    ]);

    $response = $this->actingAs($actor)->patch(route('projects.members.update', [$project, $member]), [
        'role' => ProjectMemberRole::Viewer->value,
    ]);

    $response->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseHas('project_members', [
        'id' => $member->id,
        'role' => ProjectMemberRole::Viewer->value,
    ]);

    $auditLog = AuditLog::where('action', AuditAction::ProjectMemberRoleUpdated->value)->firstOrFail();

    expect($auditLog->before_values['role'])->toBe(ProjectMemberRole::Member->value)
        ->and($auditLog->after_values['role'])->toBe(ProjectMemberRole::Viewer->value)
        ->and($auditLog->metadata['member_user_id'])->toBe($member->user_id);
});

test('an outsider without permission cannot update a member role', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $member = ProjectMember::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->patch(route('projects.members.update', [$project, $member]), [
            'role' => ProjectMemberRole::Viewer->value,
        ])
        ->assertForbidden();
});

test('the project owner cannot be demoted below manager', function () {
    $actor = userWithPermissions([PermissionName::ProjectManageMembers->value]);
    $project = Project::factory()->create();
    $ownerMember = ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $project->owner_id,
        'role' => ProjectMemberRole::Manager,
    ]);

    $this->actingAs($actor)
        ->patch(route('projects.members.update', [$project, $ownerMember]), [
            'role' => ProjectMemberRole::Member->value,
        ])
        ->assertSessionHasErrors('role');

    $this->assertDatabaseHas('project_members', [
        'id' => $ownerMember->id,
        'role' => ProjectMemberRole::Manager->value,
    ]);
});

test('a user with manage members permission can remove a member and creates audit log', function () {
    $actor = userWithPermissions([PermissionName::ProjectManageMembers->value]);
    $project = Project::factory()->create();
    $member = ProjectMember::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($actor)->delete(route('projects.members.destroy', [$project, $member]));

    $response->assertRedirect()->assertSessionHas('success');

    $this->assertDatabaseMissing('project_members', ['id' => $member->id]);

    $auditLog = AuditLog::where('action', AuditAction::ProjectMemberRemoved->value)->firstOrFail();

    expect($auditLog->metadata['member_user_id'])->toBe($member->user_id)
        ->and($auditLog->subject_id)->toBe($project->id);
});

test('an outsider without permission cannot remove a member', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $member = ProjectMember::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->delete(route('projects.members.destroy', [$project, $member]))
        ->assertForbidden();

    $this->assertDatabaseHas('project_members', ['id' => $member->id]);
});

test('the project owner cannot be removed from members', function () {
    $actor = userWithPermissions([PermissionName::ProjectManageMembers->value]);
    $project = Project::factory()->create();
    $ownerMember = ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $project->owner_id,
        'role' => ProjectMemberRole::Manager,
    ]);

    $this->actingAs($actor)
        ->delete(route('projects.members.destroy', [$project, $ownerMember]))
        ->assertSessionHasErrors('user_id');

    $this->assertDatabaseHas('project_members', ['id' => $ownerMember->id]);
});

test('a project manager without the manage members permission can still remove a member', function () {
    $manager = User::factory()->create();
    $project = Project::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'role' => ProjectMemberRole::Manager,
    ]);
    $member = ProjectMember::factory()->create(['project_id' => $project->id]);

    $this->actingAs($manager)
        ->delete(route('projects.members.destroy', [$project, $member]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('project_members', ['id' => $member->id]);
});
