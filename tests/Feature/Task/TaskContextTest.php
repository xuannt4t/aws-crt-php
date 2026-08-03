<?php

use App\Enums\PermissionName;
use App\Models\OrganizationUnit;
use App\Models\Project;
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

test('project context only shows tasks that have a project', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $project = Project::factory()->create();

    $withProject = Task::factory()->create(['project_id' => $project->id]);
    Task::factory()->create(['project_id' => null]);

    $this->actingAs($user)
        ->get(route('tasks.projects'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.context', 'project')
        ->assertJsonCount(1, 'props.tasks.data')
        ->assertJsonPath('props.tasks.data.0.id', $withProject->id);
});

test('department context only shows tasks that have an organization unit', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $unit = OrganizationUnit::factory()->create();

    $withUnit = Task::factory()->create(['organization_unit_id' => $unit->id]);

    $this->actingAs($user)
        ->get(route('tasks.departments'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.context', 'department')
        ->assertJsonCount(1, 'props.tasks.data')
        ->assertJsonPath('props.tasks.data.0.id', $withUnit->id);
});

test('context cannot be changed by a query string', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $project = Project::factory()->create();
    Task::factory()->create(['project_id' => $project->id]);
    Task::factory()->create(['project_id' => null]);

    $this->actingAs($user)
        ->get(route('tasks.projects', ['context' => 'overview']), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.context', 'project')
        ->assertJsonCount(1, 'props.tasks.data');
});

test('department context never widens the data scope of an own-scope user', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $unit = OrganizationUnit::factory()->create();

    $mine = Task::factory()->create(['organization_unit_id' => $unit->id, 'assignee_id' => $user->id]);
    Task::factory()->create(['organization_unit_id' => $unit->id]);

    $this->actingAs($user)
        ->get(route('tasks.departments'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.context', 'department')
        ->assertJsonCount(1, 'props.tasks.data')
        ->assertJsonPath('props.tasks.data.0.id', $mine->id);
});

test('project context never widens the data scope of an own-scope user', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $project = Project::factory()->create();

    $mine = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $user->id]);
    Task::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->get(route('tasks.projects'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.context', 'project')
        ->assertJsonCount(1, 'props.tasks.data')
        ->assertJsonPath('props.tasks.data.0.id', $mine->id);
});

test('dashboard redirects to the tasks overview', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('tasks.index'));
});
