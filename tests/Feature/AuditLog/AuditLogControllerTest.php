<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Models\AuditLog;
use App\Models\User;

test('a user with permission can view paginated audit logs', function () {
    $viewer = userWithPermissions([PermissionName::SystemViewAuditLogs->value]);

    AuditLog::query()->insert(
        collect(range(1, 30))
            ->map(fn (int $index): array => [
                'actor_id' => $viewer->id,
                'action' => AuditAction::UserUpdated->value,
                'subject_type' => User::class,
                'subject_id' => $viewer->id,
                'created_at' => now()->subMinutes($index),
            ])
            ->all(),
    );

    $response = $this->actingAs($viewer)->get(route('audit-logs.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AuditLogs/Index')
            ->has('auditLogs.data', 25)
            ->where('auditLogs.total', 30));
});

test('a user without permission cannot view audit logs', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('audit-logs.index'))
        ->assertForbidden();
});

test('audit logs can be filtered by actor action and date', function () {
    $viewer = userWithPermissions([PermissionName::SystemViewAuditLogs->value]);
    $matchingActor = User::factory()->create([
        'name' => 'Nguyễn Kiểm Toán',
        'email' => 'auditor@example.com',
    ]);
    $otherActor = User::factory()->create();

    AuditLog::create([
        'actor_id' => $matchingActor->id,
        'action' => AuditAction::UserRolesUpdated->value,
        'subject_type' => User::class,
        'subject_id' => $otherActor->id,
        'created_at' => now(),
    ]);

    AuditLog::create([
        'actor_id' => $otherActor->id,
        'action' => AuditAction::UserDeleted->value,
        'subject_type' => User::class,
        'subject_id' => $matchingActor->id,
        'created_at' => now()->subDays(10),
    ]);

    $response = $this->actingAs($viewer)->get(route('audit-logs.index', [
        'search' => 'auditor@example.com',
        'action' => AuditAction::UserRolesUpdated->value,
        'date_from' => now()->subDay()->toDateString(),
        'date_to' => now()->toDateString(),
    ]));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('auditLogs.data', 1)
            ->where('auditLogs.data.0.actor.email', 'auditor@example.com')
            ->where('auditLogs.data.0.action', AuditAction::UserRolesUpdated->value));
});

test('audit log filters reject unknown actions', function () {
    $viewer = userWithPermissions([PermissionName::SystemViewAuditLogs->value]);

    $this->actingAs($viewer)
        ->from(route('audit-logs.index'))
        ->get(route('audit-logs.index', ['action' => 'unknown.action']))
        ->assertRedirect(route('audit-logs.index'))
        ->assertSessionHasErrors('action');
});
