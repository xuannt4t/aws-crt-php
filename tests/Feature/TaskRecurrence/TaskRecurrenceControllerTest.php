<?php

use App\Enums\PermissionName;
use App\Enums\RecurrenceFrequency;
use App\Enums\TaskPriority;
use App\Models\OrganizationUnit;
use App\Models\Task;
use App\Models\TaskRecurrence;
use App\Models\User;
use Illuminate\Support\Carbon;

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

test('index exposes the vietnamese cadence and next occurrence for each template', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    TaskRecurrence::factory()->weekly([1])->create([
        'interval' => 1,
        'start_date' => '2026-08-01',
        'description' => 'Nhớ mang biểu mẫu X',
    ]);

    $response = $this->actingAs($viewer)->get(route('task-recurrences.index'), inertiaHeaders());

    $response->assertOk()
        ->assertJsonPath('props.recurrences.data.0.cadence', 'Thứ Hai hằng tuần')
        ->assertJsonPath('props.recurrences.data.0.description', 'Nhớ mang biểu mẫu X')
        ->assertJsonPath('props.recurrences.data.0.next_occurrence', fn ($value) => $value !== null);
});

test('show keeps the free-text description separate from the generated cadence', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    $recurrence = TaskRecurrence::factory()->weekly([1])->create([
        'interval' => 1,
        'start_date' => '2026-08-01',
        'description' => 'Nhớ mang biểu mẫu X',
    ]);

    $this->actingAs($viewer)->get(route('task-recurrences.show', $recurrence), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.recurrence.description', 'Nhớ mang biểu mẫu X')
        ->assertJsonPath('props.recurrence.cadence', 'Thứ Hai hằng tuần');
});

test('index filters by organization unit', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    $unit = OrganizationUnit::factory()->create();
    $otherUnit = OrganizationUnit::factory()->create();

    $matching = TaskRecurrence::factory()->create(['organization_unit_id' => $unit->id]);
    TaskRecurrence::factory()->create(['organization_unit_id' => $otherUnit->id]);

    $this->actingAs($viewer)->get(route('task-recurrences.index', [
        'organization_unit_id' => $unit->id,
    ]), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.recurrences.data')
        ->assertJsonPath('props.recurrences.data.0.id', $matching->id);
});

test('create and edit restrict assignable users the same way', function () {
    $creator = User::factory()->create();
    grantPermissions($creator, [PermissionName::TaskCreate->value, PermissionName::TaskUpdate->value]);
    User::factory()->create(['is_active' => true]);

    $recurrence = TaskRecurrence::factory()->daily()->create(['creator_id' => $creator->id]);

    // Không có quyền task.assign: cả hai màn hình chỉ được thấy chính mình,
    // nếu không Edit sẽ ẩn luôn ô phân công và khoá cứng người phụ trách.
    $this->actingAs($creator)->get(route('task-recurrences.create'), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.assignableUsers')
        ->assertJsonPath('props.assignableUsers.0.id', $creator->id);

    $this->actingAs($creator)->get(route('task-recurrences.edit', $recurrence), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.assignableUsers')
        ->assertJsonPath('props.assignableUsers.0.id', $creator->id);
});

test('create and edit expose every active user when the actor can assign', function () {
    $creator = User::factory()->create(['is_active' => true]);
    grantPermissions($creator, [
        PermissionName::TaskCreate->value,
        PermissionName::TaskUpdate->value,
        PermissionName::TaskAssign->value,
    ]);
    User::factory()->count(2)->create(['is_active' => true]);

    $recurrence = TaskRecurrence::factory()->daily()->create(['creator_id' => $creator->id]);

    $createCount = $this->actingAs($creator)->get(route('task-recurrences.create'), inertiaHeaders())
        ->assertOk()
        ->json('props.assignableUsers');

    $editCount = $this->actingAs($creator)->get(route('task-recurrences.edit', $recurrence), inertiaHeaders())
        ->assertOk()
        ->json('props.assignableUsers');

    expect($createCount)->toBe($editCount)
        ->and($createCount)->toHaveCount(3);
});

test('an inactive template has no next occurrence, consistently on index and show', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    $recurrence = TaskRecurrence::factory()->weekly([1])->create([
        'interval' => 1,
        'start_date' => '2026-08-01',
        'is_active' => false,
    ]);

    $this->actingAs($viewer)->get(route('task-recurrences.index'), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.recurrences.data.0.next_occurrence', null);

    $this->actingAs($viewer)->get(route('task-recurrences.show', $recurrence), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.recurrence.next_occurrence', null);
});

test('index filters by search', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);

    $matching = TaskRecurrence::factory()->create(['title' => 'Họp giao ban']);
    TaskRecurrence::factory()->create(['title' => 'Việc khác']);

    $this->actingAs($viewer)->get(route('task-recurrences.index', [
        'search' => 'Họp',
    ]), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.recurrences.data')
        ->assertJsonPath('props.recurrences.data.0.id', $matching->id);
});

test('index filters by assignee', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    $assignee = User::factory()->create();

    $matching = TaskRecurrence::factory()->create(['assignee_id' => $assignee->id]);
    TaskRecurrence::factory()->create(['assignee_id' => null]);

    $this->actingAs($viewer)->get(route('task-recurrences.index', [
        'assignee_id' => $assignee->id,
    ]), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.recurrences.data')
        ->assertJsonPath('props.recurrences.data.0.id', $matching->id);
});

test('index filters by frequency', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);

    $matching = TaskRecurrence::factory()->create([
        'frequency' => RecurrenceFrequency::Daily,
        'interval' => 1,
        'weekdays' => null,
        'day_of_month' => null,
    ]);
    TaskRecurrence::factory()->weekly([1])->create();

    $this->actingAs($viewer)->get(route('task-recurrences.index', [
        'frequency' => RecurrenceFrequency::Daily->value,
    ]), inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'props.recurrences.data')
        ->assertJsonPath('props.recurrences.data.0.id', $matching->id);
});

test('index filters by active state', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);

    $matching = TaskRecurrence::factory()->create(['is_active' => true]);
    TaskRecurrence::factory()->create(['is_active' => false]);

    $this->actingAs($viewer)->get(route('task-recurrences.index', [
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

test('switching frequency from weekly to monthly clears the abandoned weekdays column', function () {
    $creator = User::factory()->create();
    grantPermissions($creator, [PermissionName::TaskUpdate->value]);
    $recurrence = TaskRecurrence::factory()->weekly([1, 5])->create([
        'creator_id' => $creator->id,
        'start_date' => '2026-08-01',
    ]);

    expect($recurrence->weekdays)->toBe([1, 5]);

    $this->actingAs($creator)->put(route('task-recurrences.update', $recurrence), [
        'organization_unit_id' => $recurrence->organization_unit_id,
        'title' => $recurrence->title,
        'priority' => $recurrence->priority->value,
        'frequency' => RecurrenceFrequency::Monthly->value,
        'interval' => 1,
        'day_of_month' => 5,
        'start_date' => $recurrence->start_date->toDateString(),
    ])->assertRedirect(route('task-recurrences.index'));

    $recurrence->refresh();

    expect($recurrence->frequency)->toBe(RecurrenceFrequency::Monthly)
        ->and($recurrence->weekdays)->toBeNull()
        ->and($recurrence->day_of_month)->toBe(5);
});

test('switching frequency from monthly to weekly clears the abandoned day_of_month column', function () {
    $creator = User::factory()->create();
    grantPermissions($creator, [PermissionName::TaskUpdate->value]);
    $recurrence = TaskRecurrence::factory()->monthly(15)->create([
        'creator_id' => $creator->id,
        'start_date' => '2026-08-01',
    ]);

    expect($recurrence->day_of_month)->toBe(15);

    $this->actingAs($creator)->put(route('task-recurrences.update', $recurrence), [
        'organization_unit_id' => $recurrence->organization_unit_id,
        'title' => $recurrence->title,
        'priority' => $recurrence->priority->value,
        'frequency' => RecurrenceFrequency::Weekly->value,
        'interval' => 1,
        'weekdays' => [3],
        'start_date' => $recurrence->start_date->toDateString(),
    ])->assertRedirect(route('task-recurrences.index'));

    $recurrence->refresh();

    expect($recurrence->frequency)->toBe(RecurrenceFrequency::Weekly)
        ->and($recurrence->day_of_month)->toBeNull()
        ->and($recurrence->weekdays)->toBe([3]);
});

test('toggling off flips is_active without touching last_generated_for, and toggling back on skips the paused window', function () {
    Carbon::setTestNow('2026-08-10 09:00:00');

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

    // Bật lại phải đẩy mốc lên hôm qua, nếu không lần chạy kế tiếp sẽ sinh bù
    // toàn bộ 2026-08-02 → 2026-08-09 (spec mục 4.3 cấm sinh bù quãng tắt).
    expect($recurrence->is_active)->toBeTrue()
        ->and($recurrence->last_generated_for->toDateString())->toBe('2026-08-09');

    Carbon::setTestNow();
});

test('toggling a template that has never generated leaves last_generated_for null', function () {
    $creator = User::factory()->create();
    grantPermissions($creator, [PermissionName::TaskUpdate->value]);
    $recurrence = TaskRecurrence::factory()->daily()->create([
        'creator_id' => $creator->id,
        'is_active' => true,
        'last_generated_for' => null,
    ]);

    $this->actingAs($creator)->patch(route('task-recurrences.toggle', $recurrence))->assertRedirect();
    $this->actingAs($creator)->patch(route('task-recurrences.toggle', $recurrence))->assertRedirect();

    expect($recurrence->fresh()->last_generated_for)->toBeNull();
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

test('a task from a soft deleted template still exposes the template with its deleted_at flag', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $recurrence = TaskRecurrence::factory()->daily()->create();
    $task = Task::factory()->create([
        'organization_unit_id' => $recurrence->organization_unit_id,
        'creator_id' => $recurrence->creator_id,
        'task_recurrence_id' => $recurrence->id,
        'recurrence_date' => '2026-08-03',
    ]);

    $this->actingAs($viewer)->get(route('tasks.show', $task), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.task.recurrence.deleted_at', null);

    $recurrence->delete();

    // Mẫu đã xoá mềm: giao diện cần deleted_at để hiển thị nhãn tĩnh thay vì
    // link tới trang chi tiết mẫu (link đó sẽ 404).
    $this->actingAs($viewer)->get(route('tasks.show', $task), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.task.recurrence.deleted_at', fn ($value) => $value !== null);
});
