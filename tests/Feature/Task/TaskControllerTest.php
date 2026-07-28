<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\OrganizationUnit;
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
            ->has('task.status_histories', 1));
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
