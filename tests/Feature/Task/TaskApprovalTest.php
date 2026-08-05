<?php

use App\Enums\PermissionName;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskStatusHistory;
use App\Models\User;

/**
 * Luồng duyệt một cấp: đang làm → chờ kiểm tra → (hoàn thành | trả về đang làm).
 * Trước khi có luồng này, `waiting_review` chỉ có đúng một đường ra là người
 * thực hiện tự thu hồi — nghĩa là không công việc nào hoàn thành được.
 */
function reviewer(): User
{
    return userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        PermissionName::TaskApprove->value,
        PermissionName::TaskReject->value,
    ]);
}

/**
 * Factory không tự gán người phụ trách, mà không có người phụ trách thì không ai
 * nộp hay thu hồi được — nên helper này dựng sẵn người thực hiện thật.
 */
function doer(): User
{
    return userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        PermissionName::TaskSubmit->value,
    ]);
}

function taskWaitingReview(?User $assignee = null): Task
{
    return Task::factory()->create([
        'status' => TaskStatus::WaitingReview,
        'progress' => 40,
        'assignee_id' => ($assignee ?? doer())->id,
    ]);
}

test('approving a task under review completes it and pins progress to 100', function () {
    $task = taskWaitingReview();

    $this->actingAs(reviewer())
        ->patch(route('tasks.approve', $task))
        ->assertRedirect();

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::Completed)
        ->and($task->progress)->toBe(100)
        ->and($task->completed_at)->not->toBeNull();
});

test('rejecting a task sends it back to in progress and records the reason', function () {
    $task = taskWaitingReview();

    $this->actingAs(reviewer())
        ->patch(route('tasks.reject', $task), ['reason' => 'Thiếu số liệu tháng 7.'])
        ->assertRedirect();

    $task->refresh();

    expect($task->status)->toBe(TaskStatus::InProgress)
        ->and($task->completed_at)->toBeNull();

    $history = TaskStatusHistory::query()->where('task_id', $task->id)->latest('id')->first();

    expect($history->from_status)->toBe(TaskStatus::WaitingReview)
        ->and($history->to_status)->toBe(TaskStatus::InProgress)
        ->and($history->reason)->toBe('Thiếu số liệu tháng 7.');
});

test('rejecting without a reason is refused', function () {
    $task = taskWaitingReview();

    $this->actingAs(reviewer())
        ->patch(route('tasks.reject', $task), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($task->refresh()->status)->toBe(TaskStatus::WaitingReview);
});

test('a user without the approve permission cannot approve', function () {
    $task = taskWaitingReview();
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);

    $this->actingAs($user)->patch(route('tasks.approve', $task))->assertForbidden();

    expect($task->refresh()->status)->toBe(TaskStatus::WaitingReview);
});

test('the approve permission alone does not grant rejecting', function () {
    $task = taskWaitingReview();
    $user = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
        PermissionName::TaskApprove->value,
    ]);

    $this->actingAs($user)
        ->patch(route('tasks.reject', $task), ['reason' => 'Chưa đạt.'])
        ->assertForbidden();
});

test('a task outside the reviewer data scope cannot be approved by url', function () {
    $task = taskWaitingReview();
    // Có quyền duyệt nhưng phạm vi dữ liệu chỉ tới việc của chính mình, mà việc
    // này không phải của họ — không được mở khoá bằng cách gọi thẳng URL.
    $user = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskApprove->value,
    ]);

    $this->actingAs($user)->patch(route('tasks.approve', $task))->assertForbidden();

    expect($task->refresh()->status)->toBe(TaskStatus::WaitingReview);
});

test('approving is only possible from the waiting review status', function () {
    $task = Task::factory()->create(['status' => TaskStatus::InProgress]);

    $this->actingAs(reviewer())
        ->patch(route('tasks.approve', $task))
        ->assertSessionHasErrors('status');

    expect($task->refresh()->status)->toBe(TaskStatus::InProgress);
});

test('a completed task is a dead end and cannot be approved twice', function () {
    $task = Task::factory()->create(['status' => TaskStatus::Completed]);

    $this->actingAs(reviewer())
        ->patch(route('tasks.approve', $task))
        ->assertSessionHasErrors('status');
});

test('the task detail page offers approve and reject to a reviewer', function () {
    $task = taskWaitingReview();

    $this->actingAs(reviewer())
        ->get(route('tasks.show', $task), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.actions.approve', true)
        ->assertJsonPath('props.actions.reject', true);
});

test('the task detail page hides approve and reject before the work is submitted', function () {
    $task = Task::factory()->create(['status' => TaskStatus::InProgress]);

    $this->actingAs(reviewer())
        ->get(route('tasks.show', $task), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.actions.approve', false)
        ->assertJsonPath('props.actions.reject', false);
});

test('the rejection reason reaches the detail page so the doer can read it', function () {
    $task = taskWaitingReview();

    $this->actingAs(reviewer())
        ->patch(route('tasks.reject', $task), ['reason' => 'Thiếu số liệu tháng 7.']);

    // Lý do phải hiện cho chính người thực hiện, không chỉ cho người duyệt.
    $doer = User::find($task->refresh()->assignee_id);

    $this->actingAs($doer)
        ->get(route('tasks.show', $task), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.lastRejection.reason', 'Thiếu số liệu tháng 7.');
});

test('a self recall leaves no rejection banner because it carries no reason', function () {
    $doer = doer();
    $task = taskWaitingReview($doer);

    $this->actingAs($doer)->patch(route('tasks.recall', $task))->assertRedirect();

    expect($task->refresh()->status)->toBe(TaskStatus::InProgress);

    $this->actingAs($doer)
        ->get(route('tasks.show', $task), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.lastRejection', null);
});

test('the banner disappears once the work is submitted again', function () {
    $doer = doer();
    $task = taskWaitingReview($doer);

    $this->actingAs(reviewer())->patch(route('tasks.reject', $task), ['reason' => 'Chưa đạt.']);

    $this->actingAs($doer)->patch(route('tasks.submit', $task))->assertRedirect();

    $this->actingAs(reviewer())
        ->get(route('tasks.show', $task), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.lastRejection', null);
});
