<?php

use App\Enums\PermissionName;
use App\Enums\ProjectMemberRole;
use App\Enums\TaskStatus;
use App\Models\OrganizationUnit;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('own scope shows a task assigned to the user', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $mine = Task::factory()->create(['assignee_id' => $user->id]);
    $other = Task::factory()->create();

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($other->id);
});

test('own scope shows a task created by the user', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $mine = Task::factory()->create(['creator_id' => $user->id]);
    $other = Task::factory()->create();

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($other->id);
});

test('own scope does not show a colleague task in a project the user is a plain member of', function () {
    // Thay đổi hành vi có chủ đích (spec §4.1 mục 3, cập nhật cùng
    // ProjectMemberTaskVisibilityTest): thành viên thường ("own", mặc định
    // của factory) không còn tự động thấy mọi việc của dự án — chỉ những
    // thành viên có hiệu lực "all" (cột task_visibility = all hoặc vai trò
    // manager) mới thấy. Chi tiết đầy đủ nằm ở
    // tests/Feature/Project/ProjectMemberTaskVisibilityTest.php.
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $project = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $project->id, 'user_id' => $user->id]);
    $colleagueTask = Task::factory()->create(['project_id' => $project->id]);
    $otherProject = Project::factory()->create();
    $other = Task::factory()->create(['project_id' => $otherProject->id]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->not->toContain($colleagueTask->id)
        ->and($ids)->not->toContain($other->id);
});

test('own scope shows a colleague task in a project where the user has all task visibility', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $project = Project::factory()->create();
    ProjectMember::factory()->allTaskVisibility()->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role' => ProjectMemberRole::Member,
    ]);
    $colleagueTask = Task::factory()->create(['project_id' => $project->id]);
    $otherProject = Project::factory()->create();
    $other = Task::factory()->create(['project_id' => $otherProject->id]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($colleagueTask->id)
        ->and($ids)->not->toContain($other->id);
});

test('own scope shows a one level subtask of a task the user owns', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $parent = Task::factory()->create(['assignee_id' => $user->id]);
    $child = Task::factory()->create(['parent_id' => $parent->id]);
    $grandchild = Task::factory()->create(['parent_id' => $child->id]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($parent->id)
        ->and($ids)->toContain($child->id)
        ->and($ids)->not->toContain($grandchild->id);
});

test('a user without any scope permission defaults to own', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $unrelated = Task::factory()->create();

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->not->toContain($unrelated->id);
});

test('department scope sees tasks in the user unit and descendant units but not sibling units', function () {
    $root = OrganizationUnit::factory()->create();
    $child = OrganizationUnit::factory()->create(['parent_id' => $root->id]);
    $sibling = OrganizationUnit::factory()->create();

    $user = userWithPermissions(
        [PermissionName::TaskView->value, PermissionName::TaskViewDepartment->value],
        ['organization_unit_id' => $root->id],
    );

    $inRoot = Task::factory()->create(['organization_unit_id' => $root->id]);
    $inChild = Task::factory()->create(['organization_unit_id' => $child->id]);
    $inSibling = Task::factory()->create(['organization_unit_id' => $sibling->id]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($inRoot->id)
        ->and($ids)->toContain($inChild->id)
        ->and($ids)->not->toContain($inSibling->id);
});

test('department scope still sees a task owned by the user in a different unit', function () {
    $ownUnit = OrganizationUnit::factory()->create();
    $otherUnit = OrganizationUnit::factory()->create();

    $user = userWithPermissions(
        [PermissionName::TaskView->value, PermissionName::TaskViewDepartment->value],
        ['organization_unit_id' => $ownUnit->id],
    );

    $assignedElsewhere = Task::factory()->create([
        'organization_unit_id' => $otherUnit->id,
        'assignee_id' => $user->id,
    ]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($assignedElsewhere->id);
});

test('a user with department scope but no organization unit collapses to own', function () {
    $unit = OrganizationUnit::factory()->create();

    $user = userWithPermissions(
        [PermissionName::TaskView->value, PermissionName::TaskViewDepartment->value],
        ['organization_unit_id' => null],
    );

    $inUnit = Task::factory()->create(['organization_unit_id' => $unit->id]);
    $mine = Task::factory()->create(['assignee_id' => $user->id]);

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->not->toContain($inUnit->id)
        ->and($ids)->toContain($mine->id);
});

test('all scope sees every task', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);

    Task::factory()->count(3)->create();

    expect(Task::query()->visibleTo($user)->count())->toBe(3);
});

test('wider scope contains the narrower own conditions', function () {
    $user = userWithPermissions([PermissionName::TaskView->value, PermissionName::TaskViewAll->value]);
    $unrelated = Task::factory()->create();

    $ids = Task::query()->visibleTo($user)->pluck('id');

    expect($ids)->toContain($unrelated->id);
});

test('opening a task outside the scope by direct url returns 403', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $outOfScope = Task::factory()->create();

    $this->actingAs($user)
        ->get(route('tasks.show', $outOfScope))
        ->assertForbidden();
});

test('opening a task inside the scope by direct url succeeds', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);
    $inScope = Task::factory()->create(['assignee_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('tasks.show', $inScope))
        ->assertOk();
});

test('status filter still applies correctly when combined with the own scope', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);

    $matching = Task::factory()->create([
        'assignee_id' => $user->id,
        'status' => TaskStatus::Todo,
    ]);
    Task::factory()->create([
        'assignee_id' => $user->id,
        'status' => TaskStatus::Draft,
    ]);
    Task::factory()->create([
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($user)
        ->get(route('tasks.index', ['status' => TaskStatus::Todo->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $matching->id));
});

test('search filter still applies correctly when combined with the own scope', function () {
    $user = userWithPermissions([PermissionName::TaskView->value]);

    $matching = Task::factory()->create([
        'assignee_id' => $user->id,
        'title' => 'Báo cáo doanh thu quý ba',
    ]);
    Task::factory()->create([
        'assignee_id' => $user->id,
        'title' => 'Kiểm tra kho hàng',
    ]);
    Task::factory()->create([
        'title' => 'Báo cáo doanh thu tháng mười',
    ]);

    $this->actingAs($user)
        ->get(route('tasks.index', ['search' => 'Báo cáo']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.id', $matching->id));
});

test('opening the edit page of a task outside the scope returns 403', function () {
    // Trang sửa để lộ đúng phần nội dung mà trang xem đã che (tiêu đề, mô tả,
    // dự án, người phụ trách, đơn vị, hạn) nên phải chịu cùng phạm vi dữ liệu.
    $user = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskUpdate->value,
    ]);
    $outOfScope = Task::factory()->create();
    $inScope = Task::factory()->create(['assignee_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('tasks.edit', $outOfScope))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('tasks.edit', $inScope))
        ->assertOk();
});

test('downloading an attachment of a task outside the scope returns 403', function () {
    Storage::fake('local');

    $user = userWithPermissions([PermissionName::TaskView->value]);
    $outOfScope = Task::factory()->create();
    $path = "task-attachments/{$outOfScope->id}/bi-mat.pdf";
    Storage::disk('local')->put($path, 'noi dung tep');
    $attachment = TaskAttachment::factory()->for($outOfScope)->create(['path' => $path]);

    $this->actingAs($user)
        ->get(route('tasks.attachments.download', [$outOfScope, $attachment]))
        ->assertForbidden();
});

test('deleting a task outside the scope is rejected', function () {
    $user = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskDelete->value,
    ]);
    $outOfScope = Task::factory()->create();

    $this->actingAs($user)
        ->delete(route('tasks.destroy', $outOfScope))
        ->assertForbidden();

    $this->assertNotSoftDeleted($outOfScope);
});

test('assigning or dispatching a task outside the scope is rejected', function () {
    $user = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskAssign->value,
    ]);
    $outOfScope = Task::factory()->create([
        'status' => TaskStatus::Draft,
        'assignee_id' => User::factory()->create()->id,
    ]);

    expect($user->can('assign', $outOfScope))->toBeFalse()
        ->and($user->can('dispatch', $outOfScope))->toBeFalse();

    $this->actingAs($user)
        ->patch(route('tasks.dispatch', $outOfScope))
        ->assertForbidden();

    expect($outOfScope->fresh()->status)->toBe(TaskStatus::Draft);
});

test('commenting on a task outside the scope is rejected', function () {
    $user = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskComment->value,
    ]);
    $outOfScope = Task::factory()->create();

    $this->actingAs($user)
        ->post(route('tasks.comments.store', $outOfScope), ['body' => 'Không được phép trao đổi.'])
        ->assertForbidden();

    expect(TaskComment::query()->count())->toBe(0);
});

test('attaching a file to a task outside the scope is rejected', function () {
    Storage::fake('local');

    $user = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskComment->value,
    ]);
    $outOfScope = Task::factory()->create();

    $this->actingAs($user)
        ->post(route('tasks.attachments.store', $outOfScope), [
            'files' => [UploadedFile::fake()->create('bao-cao.pdf', 10, 'application/pdf')],
        ])
        ->assertForbidden();

    expect(TaskAttachment::query()->count())->toBe(0);
});
