<?php

use App\Enums\PermissionName;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use App\Models\OrganizationUnit;
use App\Models\Task;
use App\Models\TaskRecurrence;
use App\Models\User;

test('a user with task view permission can list filtered paginated recurrence templates', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    $unit = OrganizationUnit::factory()->create();

    TaskRecurrence::factory()->count(21)->create(['organization_unit_id' => $unit->id]);
    TaskRecurrence::factory()->create(['title' => 'Không khớp bộ lọc']);

    $response = $this->actingAs($viewer)->get(route('task-recurrences.index', [
        'organization_unit_id' => $unit->id,
    ]), inertiaHeaders());

    $response->assertOk()
        ->assertJsonPath('component', 'TaskRecurrences/Index')
        ->assertJsonCount(20, 'props.recurrences.data')
        ->assertJsonPath('props.recurrences.total', 21);
});

test('index exposes the vietnamese description and next occurrence for each template', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    TaskRecurrence::factory()->weekly([1])->create([
        'interval' => 1,
        'start_date' => '2026-08-01',
    ]);

    $response = $this->actingAs($viewer)->get(route('task-recurrences.index'), inertiaHeaders());

    $response->assertOk()
        ->assertJsonPath('props.recurrences.data.0.description', 'Thứ Hai hằng tuần')
        ->assertJsonPath('props.recurrences.data.0.next_occurrence', fn ($value) => $value !== null);
});

test('index filters by search assignee frequency and active state', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    $assignee = User::factory()->create();

    $matching = TaskRecurrence::factory()->create([
        'title' => 'Họp giao ban',
        'assignee_id' => $assignee->id,
        'frequency' => RecurrenceFrequency::Daily,
        'interval' => 1,
        'weekdays' => null,
        'is_active' => true,
    ]);
    TaskRecurrence::factory()->create([
        'title' => 'Việc khác',
        'is_active' => false,
    ]);

    $this->actingAs($viewer)->get(route('task-recurrences.index', [
        'search' => 'Họp',
        'assignee_id' => $assignee->id,
        'frequency' => RecurrenceFrequency::Daily->value,
        'is_active' => true,
    ]), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.recurrences.data')
        ->assertJsonPath('props.recurrences.data.0.id', $matching->id);
});

test('a user without task view permission cannot list recurrence templates', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('task-recurrences.index'), inertiaHeaders())
        ->assertForbidden();
});

test('a user with task create permission creates a recurrence template', function () {
    $creator = userWithPermissions([PermissionName::TaskCreate->value]);
    $unit = OrganizationUnit::factory()->create();

    $response = $this->actingAs($creator)->post(route('task-recurrences.store'), [
        'organization_unit_id' => $unit->id,
        'title' => 'Vệ sinh văn phòng',
        'priority' => TaskPriority::Medium->value,
        'frequency' => RecurrenceFrequency::Daily->value,
        'interval' => 1,
        'start_date' => '2026-08-03',
    ]);

    $response->assertRedirect(route('task-recurrences.index'));

    $recurrence = TaskRecurrence::where('title', 'Vệ sinh văn phòng')->firstOrFail();

    expect($recurrence->creator_id)->toBe($creator->id)
        ->and($recurrence->is_active)->toBeTrue();
});

test('a user without task create permission cannot create a recurrence template', function () {
    $user = User::factory()->create();
    $unit = OrganizationUnit::factory()->create();

    $this->actingAs($user)->post(route('task-recurrences.store'), [
        'organization_unit_id' => $unit->id,
        'title' => 'Không được phép',
        'priority' => TaskPriority::Medium->value,
        'frequency' => RecurrenceFrequency::Daily->value,
        'interval' => 1,
        'start_date' => '2026-08-03',
    ])->assertForbidden();

    $this->assertDatabaseMissing('task_recurrences', ['title' => 'Không được phép']);
});

test('a creator can update their own recurrence template', function () {
    $creator = User::factory()->create();
    grantPermissions($creator, [PermissionName::TaskUpdate->value]);
    $unit = OrganizationUnit::factory()->create();
    $recurrence = TaskRecurrence::factory()->daily()->create([
        'creator_id' => $creator->id,
        'organization_unit_id' => $unit->id,
    ]);

    $response = $this->actingAs($creator)->put(route('task-recurrences.update', $recurrence), [
        'organization_unit_id' => $unit->id,
        'title' => 'Tiêu đề mới',
        'priority' => TaskPriority::High->value,
        'frequency' => RecurrenceFrequency::Daily->value,
        'interval' => 2,
        'start_date' => $recurrence->start_date->toDateString(),
    ]);

    $response->assertRedirect(route('task-recurrences.index'));

    expect($recurrence->fresh()->title)->toBe('Tiêu đề mới')
        ->and($recurrence->fresh()->interval)->toBe(2);
});

test('a non creator with task update permission but without task assign cannot update the template', function () {
    $updater = userWithPermissions([PermissionName::TaskUpdate->value]);
    $recurrence = TaskRecurrence::factory()->daily()->create();

    $this->actingAs($updater)->put(route('task-recurrences.update', $recurrence), [
        'organization_unit_id' => $recurrence->organization_unit_id,
        'title' => 'Không được sửa',
        'priority' => TaskPriority::High->value,
        'frequency' => RecurrenceFrequency::Daily->value,
        'interval' => 1,
        'start_date' => $recurrence->start_date->toDateString(),
    ])->assertForbidden();

    expect($recurrence->fresh()->title)->not->toBe('Không được sửa');
});

test('updating a template does not change tasks already generated from it', function () {
    $creator = User::factory()->create();
    grantPermissions($creator, [PermissionName::TaskUpdate->value]);
    $recurrence = TaskRecurrence::factory()->daily()->create([
        'creator_id' => $creator->id,
        'title' => 'Tiêu đề gốc',
    ]);

    $task = Task::factory()->create([
        'task_recurrence_id' => $recurrence->id,
        'recurrence_date' => '2026-08-01',
        'title' => 'Tiêu đề gốc',
        'organization_unit_id' => $recurrence->organization_unit_id,
        'creator_id' => $creator->id,
    ]);

    $this->actingAs($creator)->put(route('task-recurrences.update', $recurrence), [
        'organization_unit_id' => $recurrence->organization_unit_id,
        'title' => 'Tiêu đề sau khi sửa',
        'priority' => TaskPriority::High->value,
        'frequency' => RecurrenceFrequency::Daily->value,
        'interval' => 1,
        'start_date' => $recurrence->start_date->toDateString(),
    ])->assertRedirect(route('task-recurrences.index'));

    expect($task->fresh()->title)->toBe('Tiêu đề gốc')
        ->and($recurrence->fresh()->title)->toBe('Tiêu đề sau khi sửa');
});

test('toggle flips is_active without touching last_generated_for', function () {
    $creator = User::factory()->create();
    grantPermissions($creator, [PermissionName::TaskUpdate->value]);
    $recurrence = TaskRecurrence::factory()->daily()->create([
        'creator_id' => $creator->id,
        'is_active' => true,
        'last_generated_for' => '2026-08-01',
    ]);

    $this->actingAs($creator)->patch(route('task-recurrences.toggle', $recurrence))
        ->assertRedirect();

    $recurrence->refresh();

    expect($recurrence->is_active)->toBeFalse()
        ->and($recurrence->last_generated_for->toDateString())->toBe('2026-08-01');

    $this->actingAs($creator)->patch(route('task-recurrences.toggle', $recurrence))
        ->assertRedirect();

    $recurrence->refresh();

    expect($recurrence->is_active)->toBeTrue()
        ->and($recurrence->last_generated_for->toDateString())->toBe('2026-08-01');
});

test('a user without update rights cannot toggle a template', function () {
    $recurrence = TaskRecurrence::factory()->daily()->create(['is_active' => true]);
    $updater = userWithPermissions([PermissionName::TaskUpdate->value]);

    $this->actingAs($updater)->patch(route('task-recurrences.toggle', $recurrence))
        ->assertForbidden();

    expect($recurrence->fresh()->is_active)->toBeTrue();
});

test('a user with task delete permission can soft delete a template without touching generated tasks', function () {
    $deleter = userWithPermissions([PermissionName::TaskDelete->value]);
    $recurrence = TaskRecurrence::factory()->daily()->create();
    $task = Task::factory()->create([
        'task_recurrence_id' => $recurrence->id,
        'recurrence_date' => '2026-08-01',
        'organization_unit_id' => $recurrence->organization_unit_id,
    ]);

    $response = $this->actingAs($deleter)->delete(route('task-recurrences.destroy', $recurrence));

    $response->assertRedirect(route('task-recurrences.index'));
    $this->assertSoftDeleted($recurrence);
    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'task_recurrence_id' => $recurrence->id,
        'deleted_at' => null,
    ]);
});

test('a user without task delete permission cannot delete a template', function () {
    $user = User::factory()->create();
    $recurrence = TaskRecurrence::factory()->daily()->create();

    $this->actingAs($user)
        ->delete(route('task-recurrences.destroy', $recurrence))
        ->assertForbidden();

    $this->assertNotSoftDeleted($recurrence);
});

test('show returns the template with generated tasks and available actions', function () {
    $creator = User::factory()->create();
    grantPermissions($creator, [
        PermissionName::TaskView->value,
        PermissionName::TaskUpdate->value,
        PermissionName::TaskDelete->value,
    ]);
    $recurrence = TaskRecurrence::factory()->daily()->create(['creator_id' => $creator->id]);
    Task::factory()->create([
        'task_recurrence_id' => $recurrence->id,
        'recurrence_date' => '2026-08-01',
        'organization_unit_id' => $recurrence->organization_unit_id,
    ]);

    $this->actingAs($creator)
        ->get(route('task-recurrences.show', $recurrence), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'TaskRecurrences/Show')
        ->assertJsonPath('props.recurrence.id', $recurrence->id)
        ->assertJsonPath('props.actions.update', true)
        ->assertJsonPath('props.actions.delete', true)
        ->assertJsonPath('props.actions.toggle', true)
        ->assertJsonCount(1, 'props.tasks.data');
});

test('a soft deleted template 404s on show edit update toggle and destroy', function () {
    $admin = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskUpdate->value,
        PermissionName::TaskDelete->value,
        PermissionName::TaskAssign->value,
    ]);
    $recurrence = TaskRecurrence::factory()->daily()->create();
    $recurrence->delete();

    $this->actingAs($admin)->get(route('task-recurrences.show', $recurrence->id))->assertNotFound();
    $this->actingAs($admin)->get(route('task-recurrences.edit', $recurrence->id))->assertNotFound();
    $this->actingAs($admin)->put(route('task-recurrences.update', $recurrence->id), [
        'organization_unit_id' => $recurrence->organization_unit_id,
        'title' => 'x',
        'priority' => TaskPriority::Medium->value,
        'frequency' => RecurrenceFrequency::Daily->value,
        'interval' => 1,
        'start_date' => '2026-08-03',
    ])->assertNotFound();
    $this->actingAs($admin)->patch(route('task-recurrences.toggle', $recurrence->id))->assertNotFound();
    $this->actingAs($admin)->delete(route('task-recurrences.destroy', $recurrence->id))->assertNotFound();
});
