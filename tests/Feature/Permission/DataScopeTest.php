<?php

use App\Enums\DataScope;
use App\Support\DataScopeResolver;

test('data scope enum has vietnamese labels', function () {
    expect(DataScope::Own->label())->toBe('Của tôi')
        ->and(DataScope::Department->label())->toBe('Đơn vị')
        ->and(DataScope::All->label())->toBe('Toàn bộ');
});

test('resolver defaults to own scope for tasks when user holds no scope permission', function () {
    $user = userWithPermissions([]);

    expect(app(DataScopeResolver::class)->forTasks($user))->toBe(DataScope::Own);
});

test('resolver defaults to own scope for projects when user holds no scope permission', function () {
    $user = userWithPermissions([]);

    expect(app(DataScopeResolver::class)->forProjects($user))->toBe(DataScope::Own);
});

test('resolver returns department scope for tasks when user only holds department permission', function () {
    $user = userWithPermissions(['task.view_department']);

    expect(app(DataScopeResolver::class)->forTasks($user))->toBe(DataScope::Department);
});

test('resolver returns all scope for tasks when user only holds all permission', function () {
    $user = userWithPermissions(['task.view_all']);

    expect(app(DataScopeResolver::class)->forTasks($user))->toBe(DataScope::All);
});

test('resolver returns widest scope for tasks when user holds multiple scope permissions', function () {
    $user = userWithPermissions(['task.view_own', 'task.view_department']);

    expect(app(DataScopeResolver::class)->forTasks($user))->toBe(DataScope::Department);

    $user2 = userWithPermissions(['task.view_own', 'task.view_department', 'task.view_all']);

    expect(app(DataScopeResolver::class)->forTasks($user2))->toBe(DataScope::All);
});

test('resolver returns department scope for projects when user only holds department permission', function () {
    $user = userWithPermissions(['project.view_department']);

    expect(app(DataScopeResolver::class)->forProjects($user))->toBe(DataScope::Department);
});

test('resolver returns all scope for projects when user only holds all permission', function () {
    $user = userWithPermissions(['project.view_all']);

    expect(app(DataScopeResolver::class)->forProjects($user))->toBe(DataScope::All);
});

test('resolver returns widest scope for projects when user holds multiple scope permissions', function () {
    $user = userWithPermissions(['project.view_own', 'project.view_all']);

    expect(app(DataScopeResolver::class)->forProjects($user))->toBe(DataScope::All);
});

test('resolver does not mix task and project scope permissions', function () {
    $user = userWithPermissions(['task.view_all']);

    expect(app(DataScopeResolver::class)->forProjects($user))->toBe(DataScope::Own);
});
