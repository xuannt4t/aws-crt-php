<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;

test('a user with permission can view filtered paginated tasks', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    $unit = OrganizationUnit::factory()->create();

    Task::factory()->count(21)->create([
        'organization_unit_id' => $unit->id,
        'creator_id' => $viewer->id,
        'status' => TaskStatus::Draft,
        'priority' => TaskPriority::High,
    ]);
    Task::factory()->create([
        'organization_unit_id' => $unit->id,
        'creator_id' => $viewer->id,
        'title' => 'Không khớp bộ lọc',
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Low,
    ]);

    $response = $this->actingAs($viewer)->get(route('tasks.index', [
        'status' => TaskStatus::Draft->value,
        'priority' => TaskPriority::High->value,
        'organization_unit_id' => $unit->id,
    ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tasks/Index')
            ->has('tasks.data', 20)
            ->where('tasks.total', 21));
});

test('a user without task view permission cannot view tasks', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tasks.index'))
        ->assertForbidden();
});

test('tasks can be filtered by multiple assignees', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    $firstAssignee = User::factory()->create();
    $secondAssignee = User::factory()->create();
    $otherAssignee = User::factory()->create();

    Task::factory()->create(['assignee_id' => $firstAssignee->id]);
    Task::factory()->create(['assignee_id' => $secondAssignee->id]);
    Task::factory()->create(['assignee_id' => $otherAssignee->id]);

    $this->actingAs($viewer)
        ->get(route('tasks.index', [
            'assignee_ids' => [$firstAssignee->id, $secondAssignee->id],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 2)
            ->where('tasks.total', 2)
            ->where('filters.assignee_ids', [$firstAssignee->id, $secondAssignee->id]));
});

test('a user with view permission can view task details and transition history', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    $task = Task::factory()->create();
    TaskStatusHistory::create([
        'task_id' => $task->id,
        'actor_id' => $viewer->id,
        'from_status' => TaskStatus::Draft,
        'to_status' => TaskStatus::Todo,
    ]);

    $this->actingAs($viewer)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tasks/Show')
            ->where('task.id', $task->id)
            ->has('activities'));

    // Trang chi tiết không còn đẩy status_histories sang frontend (xem
    // TaskActivityTest::"the task page no longer sends status histories"), nhưng việc ghi vào
    // bảng task_status_histories vẫn phải nguyên vẹn — xem trang không được làm mất lịch sử.
    $this->assertDatabaseHas('task_status_histories', [
        'task_id' => $task->id,
        'actor_id' => $viewer->id,
        'from_status' => TaskStatus::Draft->value,
        'to_status' => TaskStatus::Todo->value,
    ]);
});

test('task details expose only sanitized rich text', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    $task = Task::factory()->create([
        'description' => '<h2>Yêu cầu</h2><p><strong>An toàn</strong></p><img src=x onerror=alert(1)><script>alert(1)</script>',
    ]);

    $this->actingAs($viewer)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('task.description_html', fn (string $description) => str_contains($description, '<h2>Yêu cầu</h2>')
                && str_contains($description, '<strong>An toàn</strong>')
                && ! str_contains($description, '<script')
                && ! str_contains($description, '<img')
                && ! str_contains($description, 'onerror')));
});

test('a user with create permission creates a draft task', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($creator)->post(route('tasks.store'), [
        'organization_unit_id' => $unit->id,
        'title' => 'Chuẩn bị báo cáo tháng',
        'description' => 'Tổng hợp số liệu vận hành.',
        'priority' => TaskPriority::High->value,
        'due_at' => now()->addWeek()->format('Y-m-d H:i:s'),
    ]);

    $response->assertRedirect(route('tasks.index'));

    $task = Task::where('title', 'Chuẩn bị báo cáo tháng')->firstOrFail();

    expect($task->creator_id)->toBe($creator->id)
        ->and($task->status)->toBe(TaskStatus::Draft)
        ->and($task->progress)->toBe(0)
        ->and($task->assignee_id)->toBeNull();
});

test('rich task descriptions are sanitized before storage', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)->post(route('tasks.store'), [
        'organization_unit_id' => $unit->id,
        'title' => 'Công việc có nội dung định dạng',
        'description' => '<h2>Kết quả</h2><ul><li><strong>Báo cáo</strong></li></ul><a href="javascript:alert(1)">Liên kết xấu</a>',
        'priority' => TaskPriority::Medium->value,
    ])->assertSessionHasNoErrors();

    $description = Task::where('title', 'Công việc có nội dung định dạng')->value('description');

    expect($description)
        ->toContain('<h2>Kết quả</h2>')
        ->toContain('<strong>Báo cáo</strong>')
        ->not->toContain('javascript:')
        ->not->toContain('<script');
});

test('a user without assign permission cannot assign a task', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $assignee = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($creator)->post(route('tasks.store'), [
        'organization_unit_id' => $unit->id,
        'assignee_id' => $assignee->id,
        'title' => 'Phân công trái phép',
        'priority' => TaskPriority::Medium->value,
    ]);

    $response->assertSessionHasErrors('assignee_id');
    $this->assertDatabaseMissing('tasks', ['title' => 'Phân công trái phép']);
});

test('a task creator without assign permission can assign the task to themselves', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)
        ->get(route('tasks.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('assignableUsers', 1)
            ->where('assignableUsers.0.id', $creator->id));

    $this->actingAs($creator)
        ->post(route('tasks.store'), [
            'organization_unit_id' => $unit->id,
            'assignee_id' => $creator->id,
            'title' => 'Công việc tự nhận',
            'priority' => TaskPriority::Medium->value,
        ])
        ->assertRedirect(route('tasks.index'));

    $this->assertDatabaseHas('tasks', [
        'title' => 'Công việc tự nhận',
        'creator_id' => $creator->id,
        'assignee_id' => $creator->id,
    ]);
});

test('a user with assign permission can assign a task', function () {
    $creator = userWithPermissions([
        PermissionName::TaskCreate->value,
        PermissionName::TaskAssign->value,
    ]);
    $assignee = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($creator)->post(route('tasks.store'), [
        'organization_unit_id' => $unit->id,
        'assignee_id' => $assignee->id,
        'title' => 'Công việc đã phân công',
        'priority' => TaskPriority::Medium->value,
    ]);

    $response->assertRedirect(route('tasks.index'));
    $this->assertDatabaseHas('tasks', [
        'title' => 'Công việc đã phân công',
        'assignee_id' => $assignee->id,
    ]);
});

test('a user with update permission can update content without changing assignment', function () {
    $updater = userWithPermissions([PermissionName::TaskUpdate->value]);
    $assignee = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();
    $task = Task::factory()->create([
        'organization_unit_id' => $unit->id,
        'assignee_id' => $assignee->id,
    ]);

    $response = $this->actingAs($updater)->put(route('tasks.update', $task), [
        'organization_unit_id' => $unit->id,
        'title' => 'Tiêu đề đã cập nhật',
        'description' => 'Nội dung mới',
        'priority' => TaskPriority::Urgent->value,
        'due_at' => null,
    ]);

    $response->assertRedirect(route('tasks.index'));

    expect($task->fresh()->title)->toBe('Tiêu đề đã cập nhật')
        ->and($task->fresh()->assignee_id)->toBe($assignee->id);
});

test('task status and progress cannot be changed through CRUD update', function () {
    $updater = userWithPermissions([PermissionName::TaskUpdate->value]);
    $task = Task::factory()->create();

    $response = $this->actingAs($updater)->put(route('tasks.update', $task), [
        'organization_unit_id' => $task->organization_unit_id,
        'title' => $task->title,
        'priority' => $task->priority->value,
        'status' => TaskStatus::Completed->value,
        'progress' => 100,
    ]);

    $response->assertSessionHasErrors(['status', 'progress']);
    expect($task->fresh()->status)->toBe(TaskStatus::Draft)
        ->and($task->fresh()->progress)->toBe(0);
});

test('a user with delete permission can soft delete a task and creates audit log', function () {
    $deleter = userWithPermissions([PermissionName::TaskDelete->value]);
    $task = Task::factory()->create();

    $response = $this->actingAs($deleter)->delete(route('tasks.destroy', $task));

    $response->assertRedirect(route('tasks.index'));
    $this->assertSoftDeleted($task);

    $auditLog = AuditLog::where('action', AuditAction::TaskDeleted->value)->firstOrFail();

    expect($auditLog->actor_id)->toBe($deleter->id)
        ->and($auditLog->subject_id)->toBe($task->id);
});

test('a user without delete permission cannot delete a task', function () {
    $user = User::factory()->create();
    $task = Task::factory()->create();

    $this->actingAs($user)
        ->delete(route('tasks.destroy', $task))
        ->assertForbidden();

    $this->assertNotSoftDeleted($task);
});

test('an assignee can update progress while a task is in progress', function () {
    $assignee = userWithPermissions([PermissionName::TaskUpdate->value]);
    $task = Task::factory()->create([
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::InProgress,
        'progress' => 20,
    ]);

    $this->actingAs($assignee)
        ->patch(route('tasks.progress.update', $task), ['progress' => 65])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($task->fresh()->progress)->toBe(65);
});

test('task details show the progress action to the active assignee', function () {
    $assignee = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskUpdate->value,
    ]);
    $task = Task::factory()->create([
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::InProgress,
    ]);

    $this->actingAs($assignee)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('actions.updateProgress', true));
});

test('only the assignee can update task progress', function () {
    $assignee = User::factory()->create();
    $otherUser = userWithPermissions([PermissionName::TaskUpdate->value]);
    $task = Task::factory()->create([
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::InProgress,
        'progress' => 20,
    ]);

    $this->actingAs($otherUser)
        ->patch(route('tasks.progress.update', $task), ['progress' => 65])
        ->assertForbidden();

    expect($task->fresh()->progress)->toBe(20);
});

test('task progress must be valid and can only change while in progress', function () {
    $assignee = userWithPermissions([PermissionName::TaskUpdate->value]);
    $task = Task::factory()->create([
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::Todo,
        'progress' => 0,
    ]);

    $this->actingAs($assignee)
        ->patch(route('tasks.progress.update', $task), ['progress' => 101])
        ->assertSessionHasErrors('progress');

    $this->actingAs($assignee)
        ->patch(route('tasks.progress.update', $task), ['progress' => 50])
        ->assertSessionHasErrors('progress');

    expect($task->fresh()->progress)->toBe(0);
});

test('a task can follow dispatch start and submit transitions with immutable history', function () {
    $actor = userWithPermissions([
        PermissionName::TaskAssign->value,
        PermissionName::TaskUpdate->value,
        PermissionName::TaskSubmit->value,
    ]);
    $task = Task::factory()->create([
        'assignee_id' => $actor->id,
        'status' => TaskStatus::Draft,
    ]);

    $this->actingAs($actor)
        ->patch(route('tasks.dispatch', $task))
        ->assertRedirect();
    expect($task->fresh()->status)->toBe(TaskStatus::Todo);

    $this->actingAs($actor)
        ->patch(route('tasks.start', $task))
        ->assertRedirect();
    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);

    $this->actingAs($actor)
        ->patch(route('tasks.submit', $task))
        ->assertRedirect();
    expect($task->fresh()->status)->toBe(TaskStatus::WaitingReview);

    expect($task->statusHistories()->count())->toBe(3)
        ->and(TaskStatusHistory::query()
            ->where('task_id', $task->id)
            ->oldest('id')
            ->pluck('to_status')
            ->all())->toBe([
                TaskStatus::Todo,
                TaskStatus::InProgress,
                TaskStatus::WaitingReview,
            ]);
});

test('an assignee can recall a task waiting for review and the transition remains in history', function () {
    $assignee = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskSubmit->value,
    ]);
    $task = Task::factory()->create([
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::WaitingReview,
        'progress' => 80,
    ]);

    $this->actingAs($assignee)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('actions.recall', true));

    $this->actingAs($assignee)
        ->patch(route('tasks.recall', $task))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and($task->fresh()->progress)->toBe(80)
        ->and($task->statusHistories()->count())->toBe(1)
        ->and($task->statusHistories()->first()->from_status)->toBe(TaskStatus::WaitingReview)
        ->and($task->statusHistories()->first()->to_status)->toBe(TaskStatus::InProgress);
});

test('a non assignee cannot recall a task waiting for review', function () {
    $assignee = User::factory()->create();
    $otherUser = userWithPermissions([PermissionName::TaskSubmit->value]);
    $task = Task::factory()->create([
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::WaitingReview,
    ]);

    $this->actingAs($otherUser)
        ->patch(route('tasks.recall', $task))
        ->assertForbidden();

    expect($task->fresh()->status)->toBe(TaskStatus::WaitingReview)
        ->and($task->statusHistories()->count())->toBe(0);
});

test('a recall request cannot move a task from an unexpected status', function () {
    $assignee = userWithPermissions([PermissionName::TaskSubmit->value]);
    $task = Task::factory()->create([
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($assignee)
        ->patch(route('tasks.recall', $task))
        ->assertSessionHasErrors('status');

    expect($task->fresh()->status)->toBe(TaskStatus::Todo)
        ->and($task->statusHistories()->count())->toBe(0);
});

test('a draft task without assignee cannot be dispatched', function () {
    $dispatcher = userWithPermissions([PermissionName::TaskAssign->value]);
    $task = Task::factory()->create([
        'assignee_id' => null,
        'status' => TaskStatus::Draft,
    ]);

    $this->actingAs($dispatcher)
        ->patch(route('tasks.dispatch', $task))
        ->assertSessionHasErrors('assignee_id');

    expect($task->fresh()->status)->toBe(TaskStatus::Draft)
        ->and($task->statusHistories()->count())->toBe(0);
});

test('a non assignee cannot start a task', function () {
    $assignee = User::factory()->create();
    $otherUser = userWithPermissions([PermissionName::TaskUpdate->value]);
    $task = Task::factory()->create([
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($otherUser)
        ->patch(route('tasks.start', $task))
        ->assertForbidden();

    expect($task->fresh()->status)->toBe(TaskStatus::Todo)
        ->and($task->statusHistories()->count())->toBe(0);
});

test('an invalid task transition does not write history', function () {
    $assignee = userWithPermissions([PermissionName::TaskSubmit->value]);
    $task = Task::factory()->create([
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::Draft,
    ]);

    $this->actingAs($assignee)
        ->patch(route('tasks.submit', $task))
        ->assertSessionHasErrors('status');

    expect($task->fresh()->status)->toBe(TaskStatus::Draft)
        ->and($task->statusHistories()->count())->toBe(0);
});

test('a task can be created and linked to an open project', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value, PermissionName::ProjectView->value]);
    $unit = OrganizationUnit::factory()->create();
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);

    $response = $this->actingAs($creator)->post(route('tasks.store'), [
        'organization_unit_id' => $unit->id,
        'project_id' => $project->id,
        'title' => 'Công việc gắn dự án',
        'priority' => TaskPriority::Medium->value,
    ]);

    $response->assertRedirect(route('tasks.index'));

    $task = Task::where('title', 'Công việc gắn dự án')->firstOrFail();

    expect($task->project_id)->toBe($project->id);
});

test('a task cannot be linked to a closed project', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value, PermissionName::ProjectView->value]);
    $unit = OrganizationUnit::factory()->create();
    $closedProject = Project::factory()->create(['status' => ProjectStatus::Completed]);

    $response = $this->actingAs($creator)->post(route('tasks.store'), [
        'organization_unit_id' => $unit->id,
        'project_id' => $closedProject->id,
        'title' => 'Công việc dự án đã đóng',
        'priority' => TaskPriority::Medium->value,
    ]);

    $response->assertSessionHasErrors('project_id');
    $this->assertDatabaseMissing('tasks', ['title' => 'Công việc dự án đã đóng']);
});

test('a task cannot be updated to link a cancelled project', function () {
    $updater = userWithPermissions([PermissionName::TaskUpdate->value, PermissionName::ProjectView->value]);
    $unit = OrganizationUnit::factory()->create();
    $task = Task::factory()->create(['organization_unit_id' => $unit->id]);
    $cancelledProject = Project::factory()->create(['status' => ProjectStatus::Cancelled]);

    $response = $this->actingAs($updater)->put(route('tasks.update', $task), [
        'organization_unit_id' => $unit->id,
        'project_id' => $cancelledProject->id,
        'title' => $task->title,
        'priority' => $task->priority->value,
    ]);

    $response->assertSessionHasErrors('project_id');
    expect($task->fresh()->project_id)->toBeNull();
});

test('tasks can be filtered by project', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();

    Task::factory()->create(['project_id' => $project->id]);
    Task::factory()->create(['project_id' => $otherProject->id]);
    Task::factory()->create(['project_id' => null]);

    $this->actingAs($viewer)
        ->get(route('tasks.index', ['project_id' => $project->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.total', 1)
            ->where('filters.project_id', (string) $project->id));
});

test('task create and edit pages expose only open projects', function () {
    $creator = userWithPermissions([
        PermissionName::TaskCreate->value,
        PermissionName::TaskUpdate->value,
        PermissionName::ProjectView->value,
    ]);
    $openProject = Project::factory()->create(['status' => ProjectStatus::Active]);
    Project::factory()->create(['status' => ProjectStatus::Completed]);
    Project::factory()->create(['status' => ProjectStatus::Cancelled]);
    $task = Task::factory()->create();

    $this->actingAs($creator)
        ->get(route('tasks.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.id', $openProject->id));

    $this->actingAs($creator)
        ->get(route('tasks.edit', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.id', $openProject->id));
});

test('a project member without project view permission still sees their open project in the projects prop', function () {
    $member = userWithPermissions([PermissionName::TaskCreate->value]);
    $memberProject = Project::factory()->create(['status' => ProjectStatus::Active]);
    ProjectMember::factory()->create([
        'project_id' => $memberProject->id,
        'user_id' => $member->id,
    ]);
    Project::factory()->create(['status' => ProjectStatus::Active]);

    $this->actingAs($member)
        ->get(route('tasks.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.id', $memberProject->id));
});

test('a task cannot be attached to a project the actor cannot view', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();
    $foreignProject = Project::factory()->create(['status' => ProjectStatus::Active]);

    $this->actingAs($creator)
        ->post(route('tasks.store'), [
            'organization_unit_id' => $unit->id,
            'project_id' => $foreignProject->id,
            'title' => 'Công việc chèn trái phép',
            'priority' => TaskPriority::Medium->value,
        ])
        ->assertSessionHasErrors(['project_id' => 'Bạn không có quyền gắn công việc vào dự án này.']);

    $this->assertDatabaseMissing('tasks', ['project_id' => $foreignProject->id]);
});

test('a task cannot be moved into a project the actor cannot view', function () {
    $updater = userWithPermissions([PermissionName::TaskUpdate->value]);
    $task = Task::factory()->create(['project_id' => null]);
    $foreignProject = Project::factory()->create(['status' => ProjectStatus::Active]);

    $this->actingAs($updater)
        ->put(route('tasks.update', $task), [
            'organization_unit_id' => $task->organization_unit_id,
            'project_id' => $foreignProject->id,
            'title' => $task->title,
            'priority' => $task->priority->value,
        ])
        ->assertSessionHasErrors('project_id');

    expect($task->fresh()->project_id)->toBeNull();
});

test('a project member without project view permission can attach a task to their own project', function () {
    $member = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();
    $memberProject = Project::factory()->create(['status' => ProjectStatus::Active]);
    ProjectMember::factory()->create([
        'project_id' => $memberProject->id,
        'user_id' => $member->id,
    ]);

    $this->actingAs($member)
        ->post(route('tasks.store'), [
            'organization_unit_id' => $unit->id,
            'project_id' => $memberProject->id,
            'title' => 'Công việc hợp lệ',
            'priority' => TaskPriority::Medium->value,
        ])
        ->assertRedirect(route('tasks.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('tasks', [
        'project_id' => $memberProject->id,
        'title' => 'Công việc hợp lệ',
    ]);
});
