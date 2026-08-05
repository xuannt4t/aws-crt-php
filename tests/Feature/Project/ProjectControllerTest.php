<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('a user with project view permission can list filtered paginated projects', function () {
    $viewer = userWithPermissions([PermissionName::ProjectView->value, PermissionName::ProjectViewAll->value]);
    $unit = OrganizationUnit::factory()->create();

    Project::factory()->count(21)->create([
        'organization_unit_id' => $unit->id,
        'status' => ProjectStatus::Active,
    ]);
    Project::factory()->create([
        'name' => 'Không khớp bộ lọc',
        'status' => ProjectStatus::Completed,
    ]);

    $response = $this->actingAs($viewer)->get(route('projects.index', [
        'status' => ProjectStatus::Active->value,
        'organization_unit_id' => $unit->id,
    ]), inertiaHeaders());

    $response->assertOk()
        ->assertJsonPath('component', 'Projects/Index')
        ->assertJsonCount(20, 'props.projects.data')
        ->assertJsonPath('props.projects.total', 21);
});

test('a member with the project view gate but no scope permission sees only their own projects on the index', function () {
    // Cổng project.view là bắt buộc để mở danh sách (viewAny); phạm vi own
    // quyết định nội dung: chỉ dự án mà người này là thành viên.
    $member = userWithPermissions([PermissionName::ProjectView->value]);
    $myProject = Project::factory()->create(['name' => 'Dự án của tôi']);
    ProjectMember::factory()->create([
        'project_id' => $myProject->id,
        'user_id' => $member->id,
        'role' => ProjectMemberRole::Member,
    ]);
    Project::factory()->count(3)->create();

    $this->actingAs($member)
        ->get(route('projects.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.projects.data')
        ->assertJsonPath('props.projects.data.0.id', $myProject->id);
});

test('a user with the project view gate but without membership sees an empty project list', function () {
    $user = userWithPermissions([PermissionName::ProjectView->value]);
    Project::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('projects.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'props.projects.data');
});

test('project list exposes progress task count open task count and member count without N plus 1', function () {
    // Các số liệu tổng hợp đi qua Task::visibleTo(), nên người xem cần cả phạm
    // vi công việc mới đếm được toàn bộ việc của dự án.
    $viewer = userWithPermissions([
        PermissionName::ProjectView->value,
        PermissionName::ProjectViewAll->value,
        PermissionName::TaskViewAll->value,
    ]);
    $project = Project::factory()->create();
    ProjectMember::factory()->count(2)->create(['project_id' => $project->id]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Completed, 'progress' => 100]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'progress' => 0]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Cancelled, 'progress' => 0]);

    DB::enableQueryLog();

    $response = $this->actingAs($viewer)->get(route('projects.index'), inertiaHeaders());

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk()
        ->assertJsonPath('props.projects.data.0.progress', 50)
        ->assertJsonPath('props.projects.data.0.task_count', 3)
        ->assertJsonPath('props.projects.data.0.open_task_count', 1)
        ->assertJsonPath('props.projects.data.0.member_count', 2);

    expect($queryCount)->toBeLessThan(10);
});

test('only mine filter returns only projects the user is a member of', function () {
    $viewer = userWithPermissions([PermissionName::ProjectView->value]);
    $myProject = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $myProject->id, 'user_id' => $viewer->id]);
    Project::factory()->create();

    $this->actingAs($viewer)
        ->get(route('projects.index', ['only_mine' => true]), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.projects.data')
        ->assertJsonPath('props.projects.data.0.id', $myProject->id);
});

test('a user with create permission creates a project and becomes manager', function () {
    $creator = userWithPermissions([PermissionName::ProjectCreate->value]);
    $unit = OrganizationUnit::factory()->create();
    $owner = User::factory()->create();

    $response = $this->actingAs($creator)->post(route('projects.store'), [
        'organization_unit_id' => $unit->id,
        'owner_id' => $owner->id,
        'code' => 'PRJ-001',
        'name' => 'Dự án thí điểm',
        'description' => 'Mô tả dự án',
    ]);

    $response->assertRedirect(route('projects.index'));

    $project = Project::where('code', 'PRJ-001')->firstOrFail();

    expect($project->status)->toBe(ProjectStatus::Planning)
        ->and($project->owner_id)->toBe($owner->id);

    $this->assertDatabaseHas('project_members', [
        'project_id' => $project->id,
        'user_id' => $owner->id,
        'role' => ProjectMemberRole::Manager->value,
    ]);
});

test('a user without project create permission cannot create a project', function () {
    $user = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($user)->post(route('projects.store'), [
        'organization_unit_id' => $unit->id,
        'owner_id' => $user->id,
        'code' => 'PRJ-002',
        'name' => 'Dự án trái phép',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('projects', ['code' => 'PRJ-002']);
});

test('project code must be unique', function () {
    $creator = userWithPermissions([PermissionName::ProjectCreate->value]);
    $unit = OrganizationUnit::factory()->create();
    Project::factory()->create(['code' => 'PRJ-DUP']);

    $this->actingAs($creator)->post(route('projects.store'), [
        'organization_unit_id' => $unit->id,
        'owner_id' => $creator->id,
        'code' => 'PRJ-DUP',
        'name' => 'Dự án trùng mã',
    ])->assertSessionHasErrors('code');
});

test('project code must follow the expected format', function () {
    $creator = userWithPermissions([PermissionName::ProjectCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)->post(route('projects.store'), [
        'organization_unit_id' => $unit->id,
        'owner_id' => $creator->id,
        'code' => 'prj lowercase invalid',
        'name' => 'Dự án mã sai',
    ])->assertSessionHasErrors('code');
});

test('project end date cannot be before start date', function () {
    $creator = userWithPermissions([PermissionName::ProjectCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)->post(route('projects.store'), [
        'organization_unit_id' => $unit->id,
        'owner_id' => $creator->id,
        'code' => 'PRJ-003',
        'name' => 'Dự án ngày sai',
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-01',
    ])->assertSessionHasErrors('end_date');
});

test('a user with update permission can change the project owner and the new owner becomes manager', function () {
    $updater = userWithPermissions([PermissionName::ProjectUpdate->value, PermissionName::ProjectViewAll->value]);
    $project = Project::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $project->owner_id,
        'role' => ProjectMemberRole::Manager,
    ]);
    $newOwner = User::factory()->create();

    $response = $this->actingAs($updater)->put(route('projects.update', $project), [
        'organization_unit_id' => $project->organization_unit_id,
        'owner_id' => $newOwner->id,
        'code' => $project->code,
        'name' => 'Dự án đổi chủ',
    ]);

    $response->assertRedirect(route('projects.index'));

    expect($project->fresh()->owner_id)->toBe($newOwner->id);

    $this->assertDatabaseHas('project_members', [
        'project_id' => $project->id,
        'user_id' => $newOwner->id,
        'role' => ProjectMemberRole::Manager->value,
    ]);

    $this->assertDatabaseHas('project_members', [
        'project_id' => $project->id,
        'user_id' => $project->owner_id,
    ]);
});

test('a user with delete permission can soft delete a project and creates audit log', function () {
    $deleter = userWithPermissions([PermissionName::ProjectDelete->value, PermissionName::ProjectViewAll->value]);
    $project = Project::factory()->create();

    $response = $this->actingAs($deleter)->delete(route('projects.destroy', $project));

    $response->assertRedirect(route('projects.index'));
    $this->assertSoftDeleted($project);

    $auditLog = AuditLog::where('action', AuditAction::ProjectDeleted->value)->firstOrFail();

    expect($auditLog->actor_id)->toBe($deleter->id)
        ->and($auditLog->subject_id)->toBe($project->id);
});

test('deleting a project soft deletes its tasks, hides them from the task list and 404s on direct access, without touching other projects tasks', function () {
    $deleter = userWithPermissions([PermissionName::ProjectDelete->value, PermissionName::ProjectViewAll->value]);
    $viewer = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $tasks = Task::factory()->count(2)->create(['project_id' => $project->id]);
    $otherTask = Task::factory()->create(['project_id' => $otherProject->id]);

    $this->actingAs($deleter)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'));

    foreach ($tasks as $task) {
        $this->assertSoftDeleted($task);
    }
    $this->assertNotSoftDeleted($otherTask);

    $this->actingAs($viewer)
        ->get(route('tasks.show', $tasks->first()))
        ->assertNotFound();

    $this->actingAs($viewer)
        ->get(route('tasks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tasks.total', 1)
            ->where('tasks.data.0.id', $otherTask->id));

    $auditLog = AuditLog::where('action', AuditAction::ProjectDeleted->value)
        ->where('subject_id', $project->id)
        ->firstOrFail();

    expect($auditLog->metadata['task_count'])->toBe(2)
        ->and($auditLog->metadata['task_ids'])->toEqualCanonicalizing($tasks->pluck('id')->all());
});

test('a user without delete permission cannot delete a project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->delete(route('projects.destroy', $project))
        ->assertForbidden();

    $this->assertNotSoftDeleted($project);
});

test('a user with view permission can view project details with progress and actions', function () {
    $viewer = userWithPermissions([
        PermissionName::ProjectView->value,
        PermissionName::ProjectViewAll->value,
        PermissionName::ProjectUpdate->value,
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
    ]);
    $project = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $project->id]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Completed, 'progress' => 100]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'progress' => 0]);

    $this->actingAs($viewer)
        ->get(route('projects.show', $project), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'Projects/Show')
        ->assertJsonPath('props.project.id', $project->id)
        ->assertJsonPath('props.project.progress', 50)
        ->assertJsonPath('props.actions.update', true)
        ->assertJsonPath('props.actions.viewTasks', true)
        ->assertJsonCount(2, 'props.tasks.data')
        ->assertJsonStructure(['props' => ['members', 'tasks']]);
});

test('a project member with the view gate permission can view the project via the own scope', function () {
    // Hai lớp tách bạch (spec §3.1): thành viên tự nhiên nằm trong phạm vi
    // "own" nhờ tư cách thành viên, nhưng vẫn cần cổng project.view — không có
    // cổng thì bị chặn dù đang ở trong phạm vi, đó là mô hình hai lớp có chủ đích.
    $member = User::factory()->create();
    grantPermissions($member, [PermissionName::ProjectView->value]);
    $project = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);

    $this->actingAs($member)
        ->get(route('projects.show', $project), inertiaHeaders())
        ->assertOk();
});

test('a member without the project view gate permission cannot view the project even though membership is in scope', function () {
    $member = User::factory()->create();
    $project = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);

    $this->actingAs($member)
        ->get(route('projects.show', $project), inertiaHeaders())
        ->assertForbidden();
});

test('a user who is not a member and lacks project view permission cannot view the project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->get(route('projects.show', $project), inertiaHeaders())
        ->assertForbidden();
});

test('the project list payload does not leak the raw progress average pseudo column', function () {
    $viewer = userWithPermissions([PermissionName::ProjectView->value, PermissionName::ProjectViewAll->value]);
    $project = Project::factory()->create();
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo, 'progress' => 33]);

    $response = $this->actingAs($viewer)
        ->get(route('projects.index'), inertiaHeaders())
        ->assertOk();

    expect($response->json('props.projects.data.0'))->not->toHaveKey('progress_average');
});

test('update cannot set the project status to a closed value', function (string $status) {
    $updater = userWithPermissions([PermissionName::ProjectUpdate->value, PermissionName::ProjectViewAll->value]);
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);

    $this->actingAs($updater)
        ->put(route('projects.update', $project), [
            'organization_unit_id' => $project->organization_unit_id,
            'owner_id' => $project->owner_id,
            'code' => $project->code,
            'name' => $project->name,
            'status' => $status,
        ])
        ->assertSessionHasErrors('status');

    $project->refresh();

    expect($project->status)->toBe(ProjectStatus::Active)
        ->and($project->closed_at)->toBeNull();
})->with([ProjectStatus::Completed->value, ProjectStatus::Cancelled->value]);

test('update cannot reopen a closed project', function () {
    $updater = userWithPermissions([PermissionName::ProjectUpdate->value, PermissionName::ProjectViewAll->value]);
    $project = Project::factory()->create([
        'status' => ProjectStatus::Completed,
        'closed_at' => now(),
        'close_reason' => 'Lý do đóng dự án ngoại lệ.',
    ]);

    $this->actingAs($updater)
        ->put(route('projects.update', $project), [
            'organization_unit_id' => $project->organization_unit_id,
            'owner_id' => $project->owner_id,
            'code' => $project->code,
            'name' => $project->name,
            'status' => ProjectStatus::Active->value,
        ])
        ->assertSessionHasErrors('status');

    $project->refresh();

    expect($project->status)->toBe(ProjectStatus::Completed)
        ->and($project->closed_at)->not->toBeNull();
});

test('store cannot create a project directly in a closed status', function () {
    $creator = userWithPermissions([PermissionName::ProjectCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)
        ->post(route('projects.store'), [
            'organization_unit_id' => $unit->id,
            'owner_id' => $creator->id,
            'code' => 'PRJ-CLOSED',
            'name' => 'Dự án đóng sẵn',
            'status' => ProjectStatus::Completed->value,
        ])
        ->assertSessionHasErrors('status');

    $this->assertDatabaseMissing('projects', ['code' => 'PRJ-CLOSED']);
});

test('a project manager without the project update permission can edit ordinary fields', function () {
    $manager = User::factory()->create();
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'role' => ProjectMemberRole::Manager,
    ]);

    $this->actingAs($manager)
        ->put(route('projects.update', $project), [
            'organization_unit_id' => $project->organization_unit_id,
            'owner_id' => $project->owner_id,
            'code' => $project->code,
            'name' => 'Tên dự án do quản lý cập nhật',
        ])
        ->assertRedirect(route('projects.index'))
        ->assertSessionHasNoErrors();

    expect($project->fresh()->name)->toBe('Tên dự án do quản lý cập nhật');
});

test('a project manager without the project update permission cannot change the owner or organization unit', function () {
    $manager = User::factory()->create();
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $manager->id,
        'role' => ProjectMemberRole::Manager,
    ]);
    $outsider = User::factory()->create();
    $otherUnit = OrganizationUnit::factory()->create();

    $this->actingAs($manager)
        ->put(route('projects.update', $project), [
            'organization_unit_id' => $otherUnit->id,
            'owner_id' => $outsider->id,
            'code' => $project->code,
            'name' => $project->name,
        ])
        ->assertSessionHasErrors(['owner_id', 'organization_unit_id']);

    $project->refresh();

    expect($project->owner_id)->not->toBe($outsider->id)
        ->and($project->organization_unit_id)->not->toBe($otherUnit->id);

    $this->assertDatabaseMissing('project_members', [
        'project_id' => $project->id,
        'user_id' => $outsider->id,
    ]);
});

test('a soft deleted project is absent from the index and 404s on show edit update and close', function () {
    $admin = userWithPermissions([
        PermissionName::ProjectView->value,
        PermissionName::ProjectUpdate->value,
        PermissionName::ProjectClose->value,
    ]);
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    $project->delete();

    $this->actingAs($admin)
        ->get(route('projects.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'props.projects.data');

    $this->actingAs($admin)->get(route('projects.show', $project->id))->assertNotFound();
    $this->actingAs($admin)->get(route('projects.edit', $project->id))->assertNotFound();
    $this->actingAs($admin)->put(route('projects.update', $project->id), [
        'organization_unit_id' => $project->organization_unit_id,
        'owner_id' => $project->owner_id,
        'code' => $project->code,
        'name' => $project->name,
    ])->assertNotFound();
    $this->actingAs($admin)->patch(route('projects.close', $project->id))->assertNotFound();
});

test('a viewer role member without task view permission does not receive the project task list', function () {
    $viewerMember = User::factory()->create();
    grantPermissions($viewerMember, [PermissionName::ProjectView->value]);
    $project = Project::factory()->create();
    ProjectMember::factory()->create([
        'project_id' => $project->id,
        'user_id' => $viewerMember->id,
        'role' => ProjectMemberRole::Viewer,
    ]);
    Task::factory()->create(['project_id' => $project->id, 'title' => 'Công việc bí mật']);

    $response = $this->actingAs($viewerMember)
        ->get(route('projects.show', $project), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.actions.viewTasks', false)
        ->assertJsonPath('props.tasks', null);

    expect($response->getContent())->not->toContain('Công việc bí mật');
});
