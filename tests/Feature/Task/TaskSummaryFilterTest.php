<?php

use App\Enums\PermissionName;
use App\Enums\TaskStatus;
use App\Enums\TaskStatusBucket;
use App\Models\OrganizationUnit;
use App\Models\Task;
use App\Models\User;

/**
 * Bấm vào một ô tóm tắt thì lọc danh sách theo đúng nhóm trạng thái của ô đó.
 *
 * Ràng buộc quan trọng nhất ở đây không phải "bộ lọc chạy được", mà là số hiển
 * thị trên ô và số dòng lọc ra phải luôn bằng nhau. Cả hai cùng đọc
 * TaskStatusBucket nên không thể lệch — test dưới đây canh đúng điều đó.
 */
function viewerOfEverything(): User
{
    return userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskViewAll->value,
    ]);
}

function seedOneOfEachStatus(): void
{
    Task::factory()->create(['status' => TaskStatus::Draft]);
    Task::factory()->count(2)->create(['status' => TaskStatus::Todo]);
    Task::factory()->count(3)->create(['status' => TaskStatus::InProgress]);
    Task::factory()->create(['status' => TaskStatus::WaitingReview]);
    Task::factory()->count(2)->create(['status' => TaskStatus::Completed]);
    Task::factory()->create(['status' => TaskStatus::Cancelled]);
}

test('every summary card filters to exactly the number it displays', function () {
    seedOneOfEachStatus();
    $user = viewerOfEverything();

    $summary = $this->actingAs($user)
        ->get(route('tasks.index'), inertiaHeaders())
        ->assertOk()
        ->json('props.summary');

    foreach (TaskStatusBucket::cases() as $bucket) {
        $this->actingAs($user)
            ->get(route('tasks.index', ['bucket' => $bucket->value]), inertiaHeaders())
            ->assertOk()
            ->assertJsonPath('props.tasks.total', $summary[$bucket->value]);
    }
});

test('the not started card covers both draft and todo, not just one of them', function () {
    seedOneOfEachStatus();

    // Ô này là chỗ dễ lệch nhất vì nó gộp hai trạng thái; một bộ lọc tự viết lại
    // rất dễ chỉ bắt `todo` và trả về 2 thay vì 3.
    $this->actingAs(viewerOfEverything())
        ->get(route('tasks.index', ['bucket' => TaskStatusBucket::NotStarted->value]), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.tasks.total', 3)
        ->assertJsonPath('props.summary.not_started', 3);
});

test('a cancelled task belongs to no card and is never returned by one', function () {
    seedOneOfEachStatus();
    $user = viewerOfEverything();

    $returned = 0;

    foreach (TaskStatusBucket::cases() as $bucket) {
        $returned += $this->actingAs($user)
            ->get(route('tasks.index', ['bucket' => $bucket->value]), inertiaHeaders())
            ->json('props.tasks.total');
    }

    // 10 việc, trong đó 1 việc đã huỷ không thuộc ô nào.
    expect($returned)->toBe(9);
});

test('the overdue card filter cuts across statuses', function () {
    Task::factory()->create([
        'status' => TaskStatus::InProgress,
        'due_at' => now()->subDay(),
    ]);
    Task::factory()->create([
        'status' => TaskStatus::Todo,
        'due_at' => now()->subDay(),
    ]);
    Task::factory()->create(['status' => TaskStatus::InProgress, 'due_at' => now()->addWeek()]);

    $this->actingAs(viewerOfEverything())
        ->get(route('tasks.index', ['overdue' => 1]), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.tasks.total', 2)
        ->assertJsonPath('props.summary.overdue', 2);
});

test('a card filter combines with the other filters instead of replacing them', function () {
    $mine = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);

    Task::factory()->create(['status' => TaskStatus::Todo, 'assignee_id' => $mine->id]);
    Task::factory()->create(['status' => TaskStatus::Todo]);
    Task::factory()->create(['status' => TaskStatus::Completed, 'assignee_id' => $mine->id]);

    $this->actingAs($mine)
        ->get(route('tasks.index', [
            'bucket' => TaskStatusBucket::NotStarted->value,
            'assignee_ids' => [$mine->id],
        ]), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.tasks.total', 1);
});

test('an unknown bucket is rejected rather than silently ignored', function () {
    $this->actingAs(viewerOfEverything())
        ->get(route('tasks.index', ['bucket' => 'khong_ton_tai']), inertiaHeaders())
        ->assertSessionHasErrors('bucket');
});

test('a card filter never widens the data scope of the viewer', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);

    Task::factory()->create(['status' => TaskStatus::Todo, 'assignee_id' => $user->id]);
    Task::factory()->count(4)->create(['status' => TaskStatus::Todo]);

    $this->actingAs($user)
        ->get(route('tasks.index', ['bucket' => TaskStatusBucket::NotStarted->value]), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.tasks.total', 1);
});

test('the bucket survives alongside every other filter in one request', function () {
    $mine = viewerOfEverything();
    $unit = OrganizationUnit::factory()->create();

    $wanted = Task::factory()->create([
        'status' => TaskStatus::Todo,
        'assignee_id' => $mine->id,
        'organization_unit_id' => $unit->id,
        'title' => 'Báo cáo vận hành',
    ]);
    Task::factory()->create([
        'status' => TaskStatus::Completed,
        'assignee_id' => $mine->id,
        'organization_unit_id' => $unit->id,
        'title' => 'Báo cáo vận hành',
    ]);

    // Thanh lọc gửi lại toàn bộ query mỗi lần bấm "Áp dụng". Nếu nó không mang
    // theo `bucket` thì ô đang chọn trên phần tóm tắt lặng lẽ biến mất.
    $this->actingAs($mine)
        ->get(route('tasks.index', [
            'bucket' => TaskStatusBucket::NotStarted->value,
            'search' => 'Báo cáo',
            'organization_unit_id' => $unit->id,
            'assignee_ids' => [$mine->id],
        ]), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.tasks.total', 1)
        ->assertJsonPath('props.tasks.data.0.id', $wanted->id)
        ->assertJsonPath('props.filters.bucket', TaskStatusBucket::NotStarted->value);
});

test('overdue can be combined with a bucket rather than replacing it', function () {
    $user = viewerOfEverything();

    $overdueTodo = Task::factory()->create(['status' => TaskStatus::Todo, 'due_at' => now()->subDay()]);
    Task::factory()->create(['status' => TaskStatus::Todo, 'due_at' => now()->addWeek()]);
    Task::factory()->create(['status' => TaskStatus::InProgress, 'due_at' => now()->subDay()]);

    $this->actingAs($user)
        ->get(route('tasks.index', [
            'bucket' => TaskStatusBucket::NotStarted->value,
            'overdue' => 1,
        ]), inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.tasks.total', 1)
        ->assertJsonPath('props.tasks.data.0.id', $overdueTodo->id);
});
