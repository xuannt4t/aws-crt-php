<?php

use App\Enums\PermissionName;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;

test('a user with task view and comment permissions can comment on a task', function () {
    $author = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskComment->value,
    ]);
    $task = Task::factory()->create();

    $this->actingAs($author)
        ->post(route('tasks.comments.store', $task), [
            'body' => '  Đã hoàn tất phần API, nhờ kiểm tra giúp.  ',
        ])
        ->assertRedirect(route('tasks.show', $task))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('task_comments', [
        'task_id' => $task->id,
        'author_id' => $author->id,
        'body' => 'Đã hoàn tất phần API, nhờ kiểm tra giúp.',
    ]);
});

test('task comment requires both view and comment permissions', function (array $permissions) {
    $user = userWithPermissions($permissions);
    $task = Task::factory()->create();

    $this->actingAs($user)
        ->post(route('tasks.comments.store', $task), ['body' => 'Không được phép'])
        ->assertForbidden();

    expect(TaskComment::query()->count())->toBe(0);
})->with([
    'view only' => [[PermissionName::TaskView->value]],
    'comment only' => [[PermissionName::TaskComment->value]],
]);

test('task comment content is required and limited to five thousand characters', function () {
    $author = userWithPermissions([
        PermissionName::TaskView->value,
        PermissionName::TaskComment->value,
    ]);
    $task = Task::factory()->create();

    $this->actingAs($author)
        ->post(route('tasks.comments.store', $task), ['body' => '   '])
        ->assertSessionHasErrors('body');

    $this->actingAs($author)
        ->post(route('tasks.comments.store', $task), ['body' => str_repeat('a', 5001)])
        ->assertSessionHasErrors('body');

    expect(TaskComment::query()->count())->toBe(0);
});

test('task details show paginated comments and retain soft deleted authors', function () {
    $viewer = userWithPermissions([PermissionName::TaskView->value]);
    $task = Task::factory()->create();
    $author = User::factory()->create();

    TaskComment::factory()
        ->count(21)
        ->for($task)
        ->for($author, 'author')
        ->create();

    $author->delete();

    $this->actingAs($viewer)
        ->get(route('tasks.show', $task))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('comments.data', 20)
            ->where('comments.total', 21)
            ->where('comments.data.0.author.id', $author->id)
            ->where('actions.comment', false));
});
