<?php

use App\Enums\PermissionName;
use App\Enums\TaskStatus;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;

test('overview context shows every task within scope regardless of project or department', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $project = Project::factory()->create();
    $unit = OrganizationUnit::factory()->create();

    Task::factory()->create(['project_id' => $project->id]);
    Task::factory()->create(['organization_unit_id' => $unit->id]);
    Task::factory()->create();

    $this->actingAs($user)
        ->get(route('tasks.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.context', 'overview')
        ->assertJsonCount(3, 'props.tasks.data');
});

test('dashboard redirects to the tasks overview', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('tasks.index'));
});

// --- Cấp 1: danh sách dự án / phòng ban (spec §5.2) ---------------------

test('level 1 project list only shows projects visible to the viewer and counts only visible tasks', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);

    $myProject = Project::factory()->create();
    ProjectMember::factory()->allTaskVisibility()->create(['project_id' => $myProject->id, 'user_id' => $user->id]);

    $otherProject = Project::factory()->create();

    // Việc của tôi trong dự án tôi tham gia — được đếm.
    Task::factory()->create(['project_id' => $myProject->id, 'assignee_id' => $user->id]);
    // Việc khác trong cùng dự án nhưng không phải của tôi — cũng được đếm vì
    // thành viên có hiệu lực "all" (task_visibility = all).
    Task::factory()->create(['project_id' => $myProject->id]);
    // Việc trễ hạn trong dự án tôi tham gia.
    Task::factory()->create([
        'project_id' => $myProject->id,
        'status' => TaskStatus::Todo,
        'due_at' => now()->subDay(),
    ]);

    // Dự án tôi không tham gia — không được liệt kê.
    Task::factory()->create(['project_id' => $otherProject->id]);

    $this->actingAs($user)
        ->get(route('tasks.projects'), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.projects.data')
        ->assertJsonPath('props.projects.data.0.id', $myProject->id)
        ->assertJsonPath('props.projects.data.0.task_count', 3)
        ->assertJsonPath('props.projects.data.0.overdue_task_count', 1);
});

test('level 1 department list only shows units that have at least one task within scope', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);

    $unitWithTasks = OrganizationUnit::factory()->create();
    $unitWithoutTasks = OrganizationUnit::factory()->create();

    Task::factory()->create(['organization_unit_id' => $unitWithTasks->id]);
    Task::factory()->create([
        'organization_unit_id' => $unitWithTasks->id,
        'status' => TaskStatus::InProgress,
        'due_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get(route('tasks.departments'), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.units.data')
        ->assertJsonPath('props.units.data.0.id', $unitWithTasks->id)
        ->assertJsonPath('props.units.data.0.task_count', 2)
        ->assertJsonPath('props.units.data.0.overdue_task_count', 1);

    expect(OrganizationUnit::query()->find($unitWithoutTasks->id))->not->toBeNull();
});

// --- Cấp 2: ba khúc cố định một dự án / một phòng (spec §5.2) -----------

test('level 2 project dashboard only returns tasks of that project and hides the project filter', function () {
    $user = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        PermissionName::ProjectView->value,
        PermissionName::ProjectViewAll->value,
    ]);
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();

    $withProject = Task::factory()->create(['project_id' => $project->id]);
    Task::factory()->create(['project_id' => $otherProject->id]);
    Task::factory()->create(['project_id' => null]);

    $this->actingAs($user)
        ->get(route('tasks.projects.show', $project), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.context', 'project')
        ->assertJsonCount(1, 'props.tasks.data')
        ->assertJsonPath('props.tasks.data.0.id', $withProject->id)
        ->assertJsonMissing(['props.availableFilters' => ['project_id']]);
});

test('level 2 project dashboard cannot be widened back to another project by query string', function () {
    $user = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        PermissionName::ProjectView->value,
        PermissionName::ProjectViewAll->value,
    ]);
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();

    $withProject = Task::factory()->create(['project_id' => $project->id]);
    Task::factory()->create(['project_id' => $otherProject->id]);

    $this->actingAs($user)
        ->get(route('tasks.projects.show', $project).'?project_id='.$otherProject->id, inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.context', 'project')
        ->assertJsonCount(1, 'props.tasks.data')
        ->assertJsonPath('props.tasks.data.0.id', $withProject->id);
});

test('level 2 department dashboard includes tasks of descendant units', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $parentUnit = OrganizationUnit::factory()->create();
    $childUnit = OrganizationUnit::factory()->create(['parent_id' => $parentUnit->id]);
    $unrelatedUnit = OrganizationUnit::factory()->create();

    $parentTask = Task::factory()->create(['organization_unit_id' => $parentUnit->id]);
    $childTask = Task::factory()->create(['organization_unit_id' => $childUnit->id]);
    Task::factory()->create(['organization_unit_id' => $unrelatedUnit->id]);

    $this->actingAs($user)
        ->get(route('tasks.departments.show', $parentUnit), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.context', 'department')
        ->assertJsonCount(2, 'props.tasks.data')
        ->assertJsonPath('props.tasks.data.0.id', $childTask->id)
        ->assertJsonPath('props.tasks.data.1.id', $parentTask->id);
});

test('opening a project dashboard outside the viewer scope returns 403', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $outOfScopeProject = Project::factory()->create();
    Task::factory()->create(['project_id' => $outOfScopeProject->id]);

    $this->actingAs($user)
        ->get(route('tasks.projects.show', $outOfScopeProject), inertiaHeaders())
        ->assertForbidden();
});

test('level 2 department dashboard never widens the data scope of an own-scope user', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $unit = OrganizationUnit::factory()->create();

    $mine = Task::factory()->create(['organization_unit_id' => $unit->id, 'assignee_id' => $user->id]);
    Task::factory()->create(['organization_unit_id' => $unit->id]);

    $this->actingAs($user)
        ->get(route('tasks.departments.show', $unit), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.context', 'department')
        ->assertJsonCount(1, 'props.tasks.data')
        ->assertJsonPath('props.tasks.data.0.id', $mine->id);
});

test('level 2 project dashboard never widens the data scope of an own-scope user', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::ProjectView->value]);
    $project = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $user->id]);

    $mine = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $user->id]);
    Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('tasks.projects.show', $project), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.context', 'project')
        ->assertJsonCount(1, 'props.tasks.data')
        ->assertJsonPath('props.tasks.data.0.id', $mine->id);
});

test('level 2 summary matches the fixed task set of that project', function () {
    $user = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        PermissionName::ProjectView->value,
        PermissionName::ProjectViewAll->value,
    ]);
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();

    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Completed]);
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_at' => now()->subDay(),
    ]);
    Task::factory()->create(['project_id' => $otherProject->id]);

    $this->actingAs($user)
        ->get(route('tasks.projects.show', $project), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.summary.total', 3)
        ->assertJsonPath('props.summary.completed', 1)
        ->assertJsonPath('props.summary.overdue', 1);
});
