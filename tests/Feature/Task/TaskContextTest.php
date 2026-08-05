<?php

use App\Enums\PermissionName;
use App\Enums\TaskStatus;
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

test('dashboard redirects to the tasks overview', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('tasks.index'));
});

// --- Cấp 1: danh sách phòng ban (spec §5.2) ----------------------------

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

// --- Cấp 2: ba khúc cố định một phòng (spec §5.2) -----------------------

test('level 2 department dashboard cannot be widened back to another unit by query string', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $unit = OrganizationUnit::factory()->create();
    $otherUnit = OrganizationUnit::factory()->create();

    $withUnit = Task::factory()->create(['organization_unit_id' => $unit->id]);
    Task::factory()->create(['organization_unit_id' => $otherUnit->id]);

    $this->actingAs($user)
        ->get(route('tasks.departments.show', $unit).'?organization_unit_id='.$otherUnit->id, inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.context', 'department')
        ->assertJsonCount(1, 'props.tasks.data')
        ->assertJsonPath('props.tasks.data.0.id', $withUnit->id);
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
        ->assertJsonPath('props.tasks.data.1.id', $parentTask->id)
        ->assertJsonPath(
            'props.availableFilters',
            fn (array $filters): bool => ! in_array('organization_unit_id', $filters, true),
        );
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

test('level 2 summary matches the fixed task set of that unit', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $unit = OrganizationUnit::factory()->create();
    $otherUnit = OrganizationUnit::factory()->create();

    Task::factory()->create(['organization_unit_id' => $unit->id, 'status' => TaskStatus::Todo]);
    Task::factory()->create(['organization_unit_id' => $unit->id, 'status' => TaskStatus::Completed]);
    Task::factory()->create([
        'organization_unit_id' => $unit->id,
        'status' => TaskStatus::Todo,
        'due_at' => now()->subDay(),
    ]);
    Task::factory()->create(['organization_unit_id' => $otherUnit->id]);

    $this->actingAs($user)
        ->get(route('tasks.departments.show', $unit), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.summary.total', 3)
        ->assertJsonPath('props.summary.completed', 1)
        ->assertJsonPath('props.summary.overdue', 1);
});

test('the removed project work screen is no longer routable', function () {
    $user = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        PermissionName::ProjectView->value,
        PermissionName::ProjectViewAll->value,
    ]);
    $project = Project::factory()->create();

    // Mục "Việc dự án" đã bỏ vì trùng với module Dự án. Hai đường dẫn cũ phải
    // 404 chứ không được rơi vào `tasks/{task}` của resource route.
    $this->actingAs($user)->get('/tasks/projects')->assertNotFound();
    $this->actingAs($user)->get('/tasks/projects/'.$project->id)->assertNotFound();

    // Module Dự án và màn phòng ban vẫn còn nguyên.
    $this->actingAs($user)->get(route('projects.index'))->assertOk();
    $this->actingAs($user)->get(route('tasks.departments'))->assertOk();
});
