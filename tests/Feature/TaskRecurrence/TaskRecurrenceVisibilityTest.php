<?php

use App\Enums\PermissionName;
use App\Enums\RecurrenceFrequency;
use App\Models\OrganizationUnit;
use App\Models\Task;
use App\Models\TaskRecurrence;
use App\Models\User;

test('own scope shows a template created by the user', function () {
    $user = User::factory()->create();
    grantPermissions($user, [PermissionName::TaskView->value]);
    $mine = TaskRecurrence::factory()->create(['creator_id' => $user->id]);
    $other = TaskRecurrence::factory()->create();

    $ids = TaskRecurrence::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($other->id);
});

test('own scope shows a template assigned to the user', function () {
    $user = User::factory()->create();
    grantPermissions($user, [PermissionName::TaskView->value]);
    $mine = TaskRecurrence::factory()->create(['assignee_id' => $user->id]);
    $other = TaskRecurrence::factory()->create();

    $ids = TaskRecurrence::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($other->id);
});

test('a user without any scope permission defaults to own', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $unrelated = TaskRecurrence::factory()->create();

    $ids = TaskRecurrence::query()->visibleTo($user)->pluck('id');

    expect($ids)->not->toContain($unrelated->id);
});

test('department scope sees templates in the user unit and descendant units but not sibling units', function () {
    $root = OrganizationUnit::factory()->create();
    $child = OrganizationUnit::factory()->create(['parent_id' => $root->id]);
    $sibling = OrganizationUnit::factory()->create();

    $user = userWithPermissions(
        [PermissionName::TaskView->value, PermissionName::TaskViewDepartment->value],
        ['organization_unit_id' => $root->id],
    );

    $inRoot = TaskRecurrence::factory()->create(['organization_unit_id' => $root->id]);
    $inChild = TaskRecurrence::factory()->create(['organization_unit_id' => $child->id]);
    $inSibling = TaskRecurrence::factory()->create(['organization_unit_id' => $sibling->id]);

    $ids = TaskRecurrence::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($inRoot->id)
        ->and($ids)->toContain($inChild->id)
        ->and($ids)->not->toContain($inSibling->id);
});

test('department scope still sees a template created by the user in a different unit', function () {
    $ownUnit = OrganizationUnit::factory()->create();
    $otherUnit = OrganizationUnit::factory()->create();

    $user = User::factory()->create(['organization_unit_id' => $ownUnit->id]);
    grantPermissions($user, [PermissionName::TaskView->value, PermissionName::TaskViewDepartment->value]);

    $createdElsewhere = TaskRecurrence::factory()->create([
        'organization_unit_id' => $otherUnit->id,
        'creator_id' => $user->id,
    ]);

    $ids = TaskRecurrence::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($createdElsewhere->id);
});

test('a user with department scope but no organization unit collapses to own', function () {
    $unit = OrganizationUnit::factory()->create();

    $user = User::factory()->create(['organization_unit_id' => null]);
    grantPermissions($user, [PermissionName::TaskView->value, PermissionName::TaskViewDepartment->value]);

    $inUnit = TaskRecurrence::factory()->create(['organization_unit_id' => $unit->id]);
    $mine = TaskRecurrence::factory()->create(['creator_id' => $user->id]);

    $ids = TaskRecurrence::query()->visibleTo($user)->pluck('id');

    expect($ids)->not->toContain($inUnit->id)
        ->and($ids)->toContain($mine->id);
});

test('all scope sees every template', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);

    TaskRecurrence::factory()->count(3)->create();

    expect(TaskRecurrence::query()->visibleTo($user)->count())->toBe(3);
});

test('wider scope contains the narrower own conditions', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $unrelated = TaskRecurrence::factory()->create();

    $ids = TaskRecurrence::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($unrelated->id);
});

test('opening a template outside the scope by direct url returns 403', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $outOfScope = TaskRecurrence::factory()->create();

    $this->actingAs($user)
        ->get(route('task-recurrences.show', $outOfScope))
        ->assertForbidden();
});

test('opening a template inside the scope by direct url succeeds', function () {
    $user = User::factory()->create();
    grantPermissions($user, [PermissionName::TaskView->value]);
    $inScope = TaskRecurrence::factory()->create(['creator_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('task-recurrences.show', $inScope))
        ->assertOk();
});

test('frequency filter still applies correctly when combined with the own scope', function () {
    $user = User::factory()->create();
    grantPermissions($user, [PermissionName::TaskView->value]);

    $matching = TaskRecurrence::factory()->create([
        'creator_id' => $user->id,
        'frequency' => RecurrenceFrequency::Daily,
        'interval' => 1,
        'weekdays' => null,
        'day_of_month' => null,
    ]);
    TaskRecurrence::factory()->weekly([1])->create(['creator_id' => $user->id]);
    TaskRecurrence::factory()->create([
        'frequency' => RecurrenceFrequency::Daily,
        'interval' => 1,
        'weekdays' => null,
        'day_of_month' => null,
    ]);

    $this->actingAs($user)
        ->get(route('task-recurrences.index', ['frequency' => RecurrenceFrequency::Daily->value]), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.recurrences.data')
        ->assertJsonPath('props.recurrences.data.0.id', $matching->id);
});

test('the generated task list on the recurrence detail page is filtered by the viewer task scope', function () {
    $viewer = User::factory()->create();
    grantPermissions($viewer, [PermissionName::TaskView->value]);
    $recurrence = TaskRecurrence::factory()->daily()->create(['creator_id' => $viewer->id]);

    $mine = Task::factory()->create([
        'task_recurrence_id' => $recurrence->id,
        'recurrence_date' => '2026-08-01',
        'organization_unit_id' => $recurrence->organization_unit_id,
        'assignee_id' => $viewer->id,
    ]);
    $colleaguesTask = Task::factory()->create([
        'task_recurrence_id' => $recurrence->id,
        'recurrence_date' => '2026-08-02',
        'organization_unit_id' => $recurrence->organization_unit_id,
    ]);

    $response = $this->actingAs($viewer)
        ->get(route('task-recurrences.show', $recurrence), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.tasks.data')
        ->assertJsonPath('props.tasks.data.0.id', $mine->id);

    expect($response->json('props.tasks.data.*.id'))->not->toContain($colleaguesTask->id);
});

test('opening the edit page of a template outside the scope returns 403', function () {
    $user = User::factory()->create();
    grantPermissions($user, [
        PermissionName::TaskView->value,
        PermissionName::TaskUpdate->value,
    ]);
    $outOfScope = TaskRecurrence::factory()->create();
    $inScope = TaskRecurrence::factory()->create(['creator_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('task-recurrences.edit', $outOfScope))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('task-recurrences.edit', $inScope))
        ->assertOk();
});

test('deleting a template outside the scope is rejected', function () {
    $user = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskDelete->value,
    ]);
    $outOfScope = TaskRecurrence::factory()->create();

    $this->actingAs($user)
        ->delete(route('task-recurrences.destroy', $outOfScope))
        ->assertForbidden();

    $this->assertNotSoftDeleted($outOfScope);
});
