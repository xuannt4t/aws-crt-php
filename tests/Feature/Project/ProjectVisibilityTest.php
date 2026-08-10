<?php

use App\Enums\PermissionName;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;

test('own scope shows a project the user is a member of', function () {
    $user = userWithPermissions([PermissionName::ProjectView->value]);
    $mine = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $mine->id, 'user_id' => $user->id]);
    $other = Project::factory()->create();

    $ids = Project::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($other->id);
});

test('a user without any scope permission defaults to own', function () {
    $user = userWithPermissions([PermissionName::ProjectView->value]);
    $unrelated = Project::factory()->create();

    $ids = Project::query()->visibleTo($user)->pluck('id');

    expect($ids)->not->toContain($unrelated->id);
});

test('department scope sees projects in the user unit and descendant units but not sibling units', function () {
    $root = OrganizationUnit::factory()->create();
    $child = OrganizationUnit::factory()->create(['parent_id' => $root->id]);
    $sibling = OrganizationUnit::factory()->create();

    $user = userWithPermissions(
        [PermissionName::ProjectView->value, PermissionName::ProjectViewDepartment->value],
        ['organization_unit_id' => $root->id],
    );

    $inRoot = Project::factory()->create(['organization_unit_id' => $root->id]);
    $inChild = Project::factory()->create(['organization_unit_id' => $child->id]);
    $inSibling = Project::factory()->create(['organization_unit_id' => $sibling->id]);

    $ids = Project::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($inRoot->id)
        ->and($ids)->toContain($inChild->id)
        ->and($ids)->not->toContain($inSibling->id);
});

test('department scope still sees a project the user is a member of in a different unit', function () {
    $ownUnit = OrganizationUnit::factory()->create();
    $otherUnit = OrganizationUnit::factory()->create();

    $user = userWithPermissions(
        [PermissionName::ProjectView->value, PermissionName::ProjectViewDepartment->value],
        ['organization_unit_id' => $ownUnit->id],
    );

    $memberElsewhere = Project::factory()->create(['organization_unit_id' => $otherUnit->id]);
    ProjectMember::factory()->create(['project_id' => $memberElsewhere->id, 'user_id' => $user->id]);

    $ids = Project::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($memberElsewhere->id);
});

test('a user with department scope but no organization unit collapses to own', function () {
    $unit = OrganizationUnit::factory()->create();

    $user = userWithPermissions(
        [PermissionName::ProjectView->value, PermissionName::ProjectViewDepartment->value],
        ['organization_unit_id' => null],
    );

    $inUnit = Project::factory()->create(['organization_unit_id' => $unit->id]);
    $mine = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $mine->id, 'user_id' => $user->id]);

    $ids = Project::query()->visibleTo($user)->pluck('id');

    expect($ids)->not->toContain($inUnit->id)
        ->and($ids)->toContain($mine->id);
});

test('all scope sees every project', function () {
    $user = userWithPermissions([PermissionName::ProjectView->value, PermissionName::ProjectViewAll->value]);

    Project::factory()->count(3)->create();

    expect(Project::query()->visibleTo($user)->count())->toBe(3);
});

test('wider scope contains the narrower own conditions', function () {
    $user = userWithPermissions([PermissionName::ProjectView->value, PermissionName::ProjectViewAll->value]);
    $unrelated = Project::factory()->create();

    $ids = Project::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($unrelated->id);
});

test('opening a project outside the scope by direct url returns 403', function () {
    $user = userWithPermissions([PermissionName::ProjectView->value]);
    $outOfScope = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.show', $outOfScope))
        ->assertForbidden();
});

test('opening a project inside the scope by direct url succeeds', function () {
    $user = userWithPermissions([PermissionName::ProjectView->value]);
    $inScope = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $inScope->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('projects.show', $inScope))
        ->assertOk();
});

test('a member without the project view gate permission cannot open the project even though it is in scope', function () {
    $member = User::factory()->create();
    $project = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);

    $this->actingAs($member)
        ->get(route('projects.show', $project))
        ->assertForbidden();
});

test('status filter still applies correctly when combined with the own scope', function () {
    $user = userWithPermissions([PermissionName::ProjectView->value]);

    $matching = Project::factory()->create(['status' => ProjectStatus::Active]);
    ProjectMember::factory()->create(['project_id' => $matching->id, 'user_id' => $user->id]);

    $wrongStatus = Project::factory()->create(['status' => ProjectStatus::Completed]);
    ProjectMember::factory()->create(['project_id' => $wrongStatus->id, 'user_id' => $user->id]);

    Project::factory()->create(['status' => ProjectStatus::Active]);

    $this->actingAs($user)
        ->get(route('projects.index', ['status' => ProjectStatus::Active->value]), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.projects.data')
        ->assertJsonPath('props.projects.data.0.id', $matching->id);
});

test('a project view_all holder who is not a member only sees own tasks on the project detail page', function () {
    // Xem được trang việc dự án nhờ project.view_all (không phải thành viên), nhưng
    // chỉ có task.view (mặc định own) — không bao trùm điều kiện 3 của own
    // (thành viên việc dự án) vì họ không phải thành viên. Danh sách việc lồng phải
    // đi qua Task::visibleTo() nên chỉ thấy việc của chính mình.
    $viewer = User::factory()->create();
    grantPermissions($viewer, [
        PermissionName::ProjectView->value,
        PermissionName::ProjectViewAll->value,
        PermissionName::TaskView->value,
    ]);
    $project = Project::factory()->create();

    $mine = Task::factory()->create(['project_id' => $project->id, 'assignee_id' => $viewer->id]);
    $colleaguesTask = Task::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($viewer)
        ->get(route('projects.show', $project), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.tasks.data')
        ->assertJsonPath('props.tasks.data.0.id', $mine->id);

    expect($response->json('props.tasks.data.*.id'))->not->toContain($colleaguesTask->id);
});

test('opening the edit page of a project outside the scope returns 403', function () {
    $user = userWithPermissions([
        PermissionName::ProjectView->value,
        PermissionName::ProjectUpdate->value,
    ]);
    $outOfScope = Project::factory()->create();
    $inScope = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $inScope->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('projects.edit', $outOfScope))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('projects.edit', $inScope))
        ->assertOk();
});

test('deleting a project outside the scope is rejected', function () {
    $user = userWithPermissions([
        PermissionName::ProjectView->value,
        PermissionName::ProjectDelete->value,
    ]);
    $outOfScope = Project::factory()->create();

    $this->actingAs($user)
        ->delete(route('projects.destroy', $outOfScope))
        ->assertForbidden();

    $this->assertNotSoftDeleted($outOfScope);
});

test('the project aggregates count only the tasks the viewer can see', function () {
    // Số liệu tổng hợp phải khớp với danh sách công việc bên cạnh: người xem
    // chỉ có phạm vi own nên chỉ đếm được công việc của chính mình.
    $viewer = userWithPermissions([
        PermissionName::ProjectView->value,
        PermissionName::ProjectViewAll->value,
        PermissionName::TaskView->value,
    ]);
    $project = Project::factory()->create();

    Task::factory()->create([
        'project_id' => $project->id,
        'assignee_id' => $viewer->id,
        'status' => TaskStatus::Todo,
        'progress' => 40,
    ]);
    Task::factory()->count(3)->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'progress' => 100,
    ]);

    $this->actingAs($viewer)
        ->get(route('projects.show', $project), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.project.open_task_count', 1)
        ->assertJsonPath('props.project.progress', 40);

    $this->actingAs($viewer)
        ->get(route('projects.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.projects.data.0.task_count', 1)
        ->assertJsonPath('props.projects.data.0.open_task_count', 1)
        ->assertJsonPath('props.projects.data.0.progress', 40);
});
