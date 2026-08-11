<?php

use App\Enums\PermissionName;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use App\Models\OrganizationUnit;
use App\Models\TaskRecurrence;
use Laravel\Sanctum\Sanctum;

test('recurrence api exposes filtered visible schedules', function () {
    $user = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
    ]);
    TaskRecurrence::factory()->daily()->create(['title' => 'Báo cáo ngày']);
    TaskRecurrence::factory()->weekly()->create(['title' => 'Họp tuần']);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/task-recurrences?search=Báo cáo&frequency=daily')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Báo cáo ngày')
        ->assertJsonStructure(['data' => [['cadence', 'next_occurrence', 'permissions']]]);
});

test('recurrence api supports create update toggle detail and delete', function () {
    $unit = OrganizationUnit::factory()->create();
    $user = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        PermissionName::TaskCreate->value,
        PermissionName::TaskUpdate->value,
        PermissionName::TaskDelete->value,
    ]);
    Sanctum::actingAs($user);

    $created = $this->postJson('/api/v1/task-recurrences', [
        'organization_unit_id' => $unit->id,
        'assignee_id' => $user->id,
        'title' => 'Việc mỗi ngày',
        'description' => 'Mô tả',
        'priority' => TaskPriority::Medium->value,
        'frequency' => RecurrenceFrequency::Daily->value,
        'interval' => 1,
        'start_date' => now()->toDateString(),
        'due_time' => '17:00',
    ])->assertCreated();

    $id = $created->json('data.id');

    $this->getJson("/api/v1/task-recurrences/{$id}")
        ->assertOk()
        ->assertJsonPath('data.recurrence.id', $id)
        ->assertJsonStructure(['data' => ['tasks']]);

    $this->patchJson("/api/v1/task-recurrences/{$id}", [
        'organization_unit_id' => $unit->id,
        'assignee_id' => $user->id,
        'title' => 'Việc mỗi tuần',
        'description' => 'Mô tả mới',
        'priority' => TaskPriority::High->value,
        'frequency' => RecurrenceFrequency::Weekly->value,
        'interval' => 1,
        'weekdays' => [1, 5],
        'start_date' => now()->toDateString(),
        'due_time' => '16:00',
    ])->assertOk()->assertJsonPath('data.title', 'Việc mỗi tuần');

    $this->patchJson("/api/v1/task-recurrences/{$id}/toggle")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->deleteJson("/api/v1/task-recurrences/{$id}")->assertOk();
    $this->assertSoftDeleted('task_recurrences', ['id' => $id]);
});

test('recurrence api enforces permissions', function () {
    $user = userWithPermissions([]);
    $recurrence = TaskRecurrence::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/task-recurrences')->assertForbidden();
    $this->getJson("/api/v1/task-recurrences/{$recurrence->id}")->assertForbidden();
});
