<?php

use App\Enums\PermissionName;
use App\Enums\TaskStatus;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;

test('own scope shows a task assigned to the user', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $mine = Task::factory()->create(['assignee_id' => $user->id]);
    $other = Task::factory()->create();

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($other->id);
});

test('own scope shows a task created by the user', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $mine = Task::factory()->create(['creator_id' => $user->id]);
    $other = Task::factory()->create();

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($other->id);
});

test('own scope shows a task in a project the user is a member of', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $project = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $user->id]);
    $mine = Task::factory()->create(['project_id' => $project->id]);
    $otherProject = Project::factory()->create();
    $other = Task::factory()->create(['project_id' => $otherProject->id]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($other->id);
});

test('own scope shows a one level subtask of a task the user owns', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $parent = Task::factory()->create(['assignee_id' => $user->id]);
    $child = Task::factory()->create(['parent_id' => $parent->id]);
    $grandchild = Task::factory()->create(['parent_id' => $child->id]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($parent->id)
        ->and($ids)->toContain($child->id)
        ->and($ids)->not->toContain($grandchild->id);
});

test('a user without any scope permission defaults to own', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $unrelated = Task::factory()->create();

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->not->toContain($unrelated->id);
});

test('department scope sees tasks in the user unit and descendant units but not sibling units', function () {
    $root = OrganizationUnit::factory()->create();
    $child = OrganizationUnit::factory()->create(['parent_id' => $root->id]);
    $sibling = OrganizationUnit::factory()->create();

    $user = userWithPermissions(
        [PermissionName::TaskView->value, PermissionName::TaskViewDepartment->value],
        ['organization_unit_id' => $root->id],
    );

    $inRoot = Task::factory()->create(['organization_unit_id' => $root->id]);
    $inChild = Task::factory()->create(['organization_unit_id' => $child->id]);
    $inSibling = Task::factory()->create(['organization_unit_id' => $sibling->id]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($inRoot->id)
        ->and($ids)->toContain($inChild->id)
        ->and($ids)->not->toContain($inSibling->id);
});

test('department scope still sees a task owned by the user in a different unit', function () {
    $ownUnit = OrganizationUnit::factory()->create();
    $otherUnit = OrganizationUnit::factory()->create();

    $user = userWithPermissions(
        [PermissionName::TaskView->value, PermissionName::TaskViewDepartment->value],
        ['organization_unit_id' => $ownUnit->id],
    );

    $assignedElsewhere = Task::factory()->create([
        'organization_unit_id' => $otherUnit->id,
        'assignee_id' => $user->id,
    ]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($assignedElsewhere->id);
});

test('a user with department scope but no organization unit collapses to own', function () {
    $unit = OrganizationUnit::factory()->create();

    $user = userWithPermissions(
        [PermissionName::TaskView->value, PermissionName::TaskViewDepartment->value],
        ['organization_unit_id' => null],
    );

    $inUnit = Task::factory()->create(['organization_unit_id' => $unit->id]);
    $mine = Task::factory()->create(['assignee_id' => $user->id]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->not->toContain($inUnit->id)
        ->and($ids)->toContain($mine->id);
});

test('all scope sees every task', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);

    Task::factory()->count(3)->create();

    expect(Task::query()->visibleTo($user)->count())->toBe(3);
});

test('wider scope contains the narrower own conditions', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $unrelated = Task::factory()->create();

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($unrelated->id);
});

test('opening a task outside the scope by direct url returns 403', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $outOfScope = Task::factory()->create();

    $this->actingAs($user)
        ->get(route('tasks.show', $outOfScope))
        ->assertForbidden();
});

test('opening a task inside the scope by direct url succeeds', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $inScope = Task::factory()->create(['assignee_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('tasks.show', $inScope))
        ->assertOk();
});

test('status filter still applies correctly when combined with the own scope', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);

    $matching = Task::factory()->create([
        'assignee_id' => $user->id,
        'status' => TaskStatus::Todo,
    ]);
    Task::factory()->create([
        'assignee_id' => $user->id,
        'status' => TaskStatus::Draft,
    ]);
    Task::factory()->create([
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($user)
        ->get(route('tasks.index', ['status' => TaskStatus::Todo->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $matching->id));
});

test('search filter still applies correctly when combined with the own scope', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);

    $matching = Task::factory()->create([
        'assignee_id' => $user->id,
        'title' => 'Báo cáo doanh thu quý ba',
    ]);
    Task::factory()->create([
        'assignee_id' => $user->id,
        'title' => 'Kiểm tra kho hàng',
    ]);
    Task::factory()->create([
        'title' => 'Báo cáo doanh thu tháng mười',
    ]);

    $this->actingAs($user)
        ->get(route('tasks.index', ['search' => 'Báo cáo']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $matching->id));
});
