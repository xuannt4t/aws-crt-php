<?php

use App\Enums\PermissionName;
use App\Enums\ProjectStatus;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskRecurrence;
use App\Models\User;

function baseRecurrencePayload(OrganizationUnit $unit): array
{
    return [
        'organization_unit_id' => $unit->id,
        'title' => 'Báo cáo tuần',
        'priority' => TaskPriority::Medium->value,
        'frequency' => RecurrenceFrequency::Daily->value,
        'interval' => 1,
        'start_date' => '2026-08-03',
    ];
}

test('weekly frequency requires weekdays with at least one valid entry', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'frequency' => RecurrenceFrequency::Weekly->value,
    ])->assertSessionHasErrors('weekdays');
});

test('weekly frequency rejects weekdays outside the 1 to 7 range or duplicated', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'frequency' => RecurrenceFrequency::Weekly->value,
        'weekdays' => [0, 8],
    ])->assertSessionHasErrors(['weekdays.0', 'weekdays.1']);

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'frequency' => RecurrenceFrequency::Weekly->value,
        'weekdays' => [1, 1],
    ])->assertSessionHasErrors(['weekdays.0', 'weekdays.1']);
});

test('weekdays must be absent for non weekly frequencies', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'frequency' => RecurrenceFrequency::Daily->value,
        'weekdays' => [1],
    ])->assertSessionHasErrors('weekdays');
});

test('monthly and quarterly frequencies require day of month between 1 and 31', function (string $frequency) {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'frequency' => $frequency,
    ])->assertSessionHasErrors('day_of_month');

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'frequency' => $frequency,
        'day_of_month' => 32,
    ])->assertSessionHasErrors('day_of_month');
})->with([RecurrenceFrequency::Monthly->value, RecurrenceFrequency::Quarterly->value]);

test('day of month must be absent for daily and weekly frequencies', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'frequency' => RecurrenceFrequency::Daily->value,
        'day_of_month' => 5,
    ])->assertSessionHasErrors('day_of_month');
});

test('interval must be at least 1 and at most 52', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'interval' => 0,
    ])->assertSessionHasErrors('interval');

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'interval' => 53,
    ])->assertSessionHasErrors('interval');
});

test('title priority start_date and organization unit are required', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        'interval' => 1,
        'frequency' => RecurrenceFrequency::Daily->value,
    ])->assertSessionHasErrors(['organization_unit_id', 'title', 'priority', 'start_date']);
});

test('planned quantity requires a quantity unit', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'planned_quantity' => 10,
    ])->assertSessionHasErrors('quantity_unit');
});

test('due_time must follow H:i format', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'due_time' => 'not-a-time',
    ])->assertSessionHasErrors('due_time');
});

test('only a user with task assign permission can assign the template to someone else', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'assignee_id' => $otherUser->id,
    ])->assertSessionHasErrors('assignee_id');

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'assignee_id' => $creator->id,
    ])->assertSessionHasNoErrors();
});

test('a user with task assign permission can assign the template to someone else', function () {
    $creator = userWithPermissions([
        PermissionName::TaskCreate->value,
        PermissionName::TaskAssign->value,
    ]);
    $unit = OrganizationUnit::factory()->create();
    $otherUser = User::factory()->create(['is_active' => true]);

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'assignee_id' => $otherUser->id,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('task_recurrences', [
        'title' => 'Báo cáo tuần',
        'assignee_id' => $otherUser->id,
    ]);
});

test('project id must belong to an open project', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();
    $closedProject = Project::factory()->create(['status' => ProjectStatus::Completed]);

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'project_id' => $closedProject->id,
    ])->assertSessionHasErrors('project_id');
});

test('project id must be visible to the user', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();
    $invisibleProject = Project::factory()->create(['status' => ProjectStatus::Active]);

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'project_id' => $invisibleProject->id,
    ])->assertSessionHasErrors('project_id');
});

test('an open and visible project can be selected', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value, PermissionName::ProjectView->value]);
    $unit = OrganizationUnit::factory()->create();
    $project = Project::factory()->create(['status' => ProjectStatus::Active]);
    ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $creator->id]);

    $this->actingAs($creator)->post(route('task-recurrences.store'), [
        ...baseRecurrencePayload($unit),
        'project_id' => $project->id,
    ])->assertSessionHasNoErrors();
});

test('editing a template does not modify tasks already generated', function () {
    $creator = User::factory()->create();
    grantPermissions($creator, [PermissionName::TaskUpdate->value]);
    $recurrence = TaskRecurrence::factory()->weekly([1])->create([
        'creator_id' => $creator->id,
        'title' => 'Trước khi sửa',
        'day_of_month' => null,
    ]);

    $task = Task::factory()->create([
        'task_recurrence_id' => $recurrence->id,
        'recurrence_date' => '2026-08-03',
        'title' => 'Trước khi sửa',
        'priority' => TaskPriority::Low,
        'organization_unit_id' => $recurrence->organization_unit_id,
        'creator_id' => $creator->id,
    ]);

    $this->actingAs($creator)->put(route('task-recurrences.update', $recurrence), [
        'organization_unit_id' => $recurrence->organization_unit_id,
        'title' => 'Sau khi sửa',
        'priority' => TaskPriority::Urgent->value,
        'frequency' => RecurrenceFrequency::Weekly->value,
        'interval' => 1,
        'weekdays' => [2],
        'start_date' => $recurrence->start_date->toDateString(),
    ])->assertRedirect(route('task-recurrences.index'));

    expect($task->fresh()->title)->toBe('Trước khi sửa')
        ->and($task->fresh()->priority)->toBe(TaskPriority::Low)
        ->and($recurrence->fresh()->title)->toBe('Sau khi sửa')
        ->and($recurrence->fresh()->priority)->toBe(TaskPriority::Urgent);
});
