<?php

use App\Enums\PermissionName;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectTaskVisibility;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

test('a new project member defaults to own task visibility', function () {
    $member = ProjectMember::factory()->create();

    expect($member->task_visibility)->toBe(ProjectTaskVisibility::Own)
        ->and($member->effectiveTaskVisibility())->toBe(ProjectTaskVisibility::Own);
});

test('effective task visibility is all for a manager even when the column says own', function () {
    $member = ProjectMember::factory()->create([
        'role' => ProjectMemberRole::Manager,
        'task_visibility' => ProjectTaskVisibility::Own,
    ]);

    expect($member->effectiveTaskVisibility())->toBe(ProjectTaskVisibility::All);
});

test('effective task visibility is all for a member explicitly granted all', function () {
    $member = ProjectMember::factory()->allTaskVisibility()->create([
        'role' => ProjectMemberRole::Member,
    ]);

    expect($member->effectiveTaskVisibility())->toBe(ProjectTaskVisibility::All);
});

test('an own visibility member does not see a colleague task in the same project', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $project = Project::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role' => ProjectMemberRole::Member,
        'task_visibility' => ProjectTaskVisibility::Own,
    ]);
    $colleagueTask = Task::factory()->create(['project_id' => $project->id]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->not->toContain($colleagueTask->id);
});

test('an own visibility member still sees a task assigned to them in the project', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $project = Project::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role' => ProjectMemberRole::Member,
        'task_visibility' => ProjectTaskVisibility::Own,
    ]);
    $assigned = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $user->id]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($assigned->id);
});

test('an own visibility member still sees a task they created in the project', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $project = Project::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role' => ProjectMemberRole::Member,
        'task_visibility' => ProjectTaskVisibility::Own,
    ]);
    $created = Task::factory()->create(['project_id' => $project->id, 'creator_id' => $user->id]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($created->id);
});

test('an all visibility member sees a colleague task in the same project', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $project = Project::factory()->create();
    ProjectMember::factory()->allTaskVisibility()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role' => ProjectMemberRole::Member,
    ]);
    $colleagueTask = Task::factory()->create(['project_id' => $project->id]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($colleagueTask->id);
});

test('a manager sees a colleague task in the project even when the column says own', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $project = Project::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role' => ProjectMemberRole::Manager,
        'task_visibility' => ProjectTaskVisibility::Own,
    ]);
    $colleagueTask = Task::factory()->create(['project_id' => $project->id]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($colleagueTask->id);
});

test('migration backfills task_visibility from existing role data', function () {
    // RefreshDatabase already ran this migration as part of migrate:fresh, before
    // any project member existed. Roll it back so `up()` runs again against rows
    // inserted now, to exercise the backfill logic.
    Artisan::call('migrate:rollback', [
        '--path' => 'database/migrations/2026_08_03_150000_add_task_visibility_to_project_members_table.php',
        '--realpath' => false,
    ]);

    $project = Project::factory()->create();
    $managerUser = User::factory()->create();
    $memberUser = User::factory()->create();

    DB::table('project_members')->insert([
        [
            'project_id' => $project->id,
            'user_id' => $managerUser->id,
            'role' => ProjectMemberRole::Manager->value,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'project_id' => $project->id,
            'user_id' => $memberUser->id,
            'role' => ProjectMemberRole::Member->value,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_08_03_150000_add_task_visibility_to_project_members_table.php',
        '--realpath' => false,
    ]);

    expect(DB::table('project_members')->where('user_id', $managerUser->id)->value('task_visibility'))->toBe('all')
        ->and(DB::table('project_members')->where('user_id', $memberUser->id)->value('task_visibility'))->toBe('own');
});
