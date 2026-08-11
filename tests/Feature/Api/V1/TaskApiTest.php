<?php

use App\Enums\PermissionName;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\OrganizationUnit;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function apiTaskUser(array $extraPermissions = []): User
{
    return userWithPermissions(array_values(array_unique([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        ...$extraPermissions,
    ])));
}

test('task list applies visibility filters and pagination', function () {
    $user = apiTaskUser();
    Task::factory()->create(['title' => 'Khớp bộ lọc', 'priority' => TaskPriority::Urgent]);
    Task::factory()->create(['title' => 'Không khớp', 'priority' => TaskPriority::Low]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/tasks?search=Khớp&priority=urgent')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Khớp bộ lọc')
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonStructure(['meta' => ['summary']]);
});

test('task crud uses existing validation actions and policies', function () {
    $unit = OrganizationUnit::factory()->create();
    $user = apiTaskUser([
        PermissionName::TaskCreate->value,
        PermissionName::TaskUpdate->value,
        PermissionName::TaskDelete->value,
        PermissionName::TaskAssign->value,
    ]);
    Sanctum::actingAs($user);

    $created = $this->postJson('/api/v1/tasks', [
        'organization_unit_id' => $unit->id,
        'assignee_id' => $user->id,
        'title' => 'Công việc từ app',
        'description' => '<script>x</script><p>Nội dung</p>',
        'priority' => TaskPriority::High->value,
    ])->assertCreated();

    $taskId = $created->json('data.id');
    $created->assertJsonPath('data.status', TaskStatus::Draft->value);

    $this->getJson("/api/v1/tasks/{$taskId}")
        ->assertOk()
        ->assertJsonPath('data.task.id', $taskId)
        ->assertJsonMissing(['<script>x</script>']);

    $this->patchJson("/api/v1/tasks/{$taskId}", [
        'organization_unit_id' => $unit->id,
        'assignee_id' => $user->id,
        'title' => 'Đã cập nhật',
        'description' => 'Mô tả mới',
        'priority' => TaskPriority::Urgent->value,
    ])->assertOk()->assertJsonPath('data.title', 'Đã cập nhật');

    $this->deleteJson("/api/v1/tasks/{$taskId}")->assertOk();
    $this->assertSoftDeleted('tasks', ['id' => $taskId]);
});

test('task workflow exposes every existing status and progress action', function () {
    $user = apiTaskUser([
        PermissionName::TaskUpdate->value,
        PermissionName::TaskAssign->value,
        PermissionName::TaskSubmit->value,
        PermissionName::TaskApprove->value,
        PermissionName::TaskReject->value,
    ]);
    $task = Task::factory()->create([
        'creator_id' => $user->id,
        'assignee_id' => $user->id,
        'status' => TaskStatus::Draft,
    ]);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/tasks/{$task->id}/dispatch")->assertOk()->assertJsonPath('data.status', 'todo');
    $this->patchJson("/api/v1/tasks/{$task->id}/start")->assertOk()->assertJsonPath('data.status', 'in_progress');
    $this->patchJson("/api/v1/tasks/{$task->id}/progress", ['progress' => 60])
        ->assertOk()->assertJsonPath('data.progress', 60);
    $this->patchJson("/api/v1/tasks/{$task->id}/submit")->assertOk()->assertJsonPath('data.status', 'waiting_review');
    $this->patchJson("/api/v1/tasks/{$task->id}/reject", ['reason' => 'Cần bổ sung'])
        ->assertOk()->assertJsonPath('data.status', 'in_progress');
    $this->patchJson("/api/v1/tasks/{$task->id}/submit")->assertOk();
    $this->patchJson("/api/v1/tasks/{$task->id}/approve", ['reason' => 'Đạt'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.progress', 100);
});

test('task comments attachments departments and quantity are available to the app', function () {
    Storage::fake('local');
    $unit = OrganizationUnit::factory()->create();
    $user = apiTaskUser([
        PermissionName::TaskUpdate->value,
        PermissionName::TaskComment->value,
    ]);
    $task = Task::factory()->withQuantity(10)->create([
        'organization_unit_id' => $unit->id,
        'creator_id' => $user->id,
        'assignee_id' => $user->id,
        'status' => TaskStatus::InProgress,
    ]);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/tasks/{$task->id}/quantity", ['actual_quantity' => 4])
        ->assertOk()
        ->assertJsonPath('data.actual_quantity', 4)
        ->assertJsonPath('data.progress', 40);

    $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Trao đổi từ app'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Trao đổi từ app');

    $upload = $this->post("/api/v1/tasks/{$task->id}/attachments", [
        'files' => [UploadedFile::fake()->image('evidence.png')],
    ], ['Accept' => 'application/json'])->assertCreated();

    $attachmentId = $upload->json('data.0.id');
    $this->get("/api/v1/tasks/{$task->id}/attachments/{$attachmentId}")->assertOk();
    $this->deleteJson("/api/v1/tasks/{$task->id}/attachments/{$attachmentId}")->assertOk();

    $this->getJson('/api/v1/task-departments')->assertOk()->assertJsonFragment(['id' => $unit->id]);
    $this->getJson("/api/v1/task-departments/{$unit->id}")->assertOk()->assertJsonFragment(['id' => $task->id]);
});

test('task routes enforce server side permissions', function () {
    $user = User::factory()->create();
    $task = Task::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/tasks')->assertForbidden();
    $this->getJson("/api/v1/tasks/{$task->id}")->assertForbidden();
    $this->deleteJson("/api/v1/tasks/{$task->id}")->assertForbidden();
});

test('an invalid task workflow transition returns a conflict response', function () {
    $user = apiTaskUser([PermissionName::TaskUpdate->value]);
    $task = Task::factory()->create([
        'assignee_id' => $user->id,
        'status' => TaskStatus::Draft,
    ]);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/tasks/{$task->id}/start")
        ->assertConflict()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['status']]);
});
