<?php

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\ApiRefreshSession;
use App\Models\AuditLog;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Services\ApiTokenService;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

test('organization unit api supports listing and crud', function () {
    $admin = userWithPermissions([
        PermissionName::OrganizationView->value,
        PermissionName::OrganizationCreate->value,
        PermissionName::OrganizationUpdate->value,
        PermissionName::OrganizationDelete->value,
    ]);
    Sanctum::actingAs($admin);

    $created = $this->postJson('/api/v1/organization-units', [
        'name' => 'Khối ứng dụng',
        'code' => 'APP',
        'is_active' => true,
    ])->assertCreated();
    $id = $created->json('data.id');

    $this->getJson('/api/v1/organization-units')
        ->assertOk()
        ->assertJsonFragment(['code' => 'APP']);
    $this->getJson("/api/v1/organization-units/{$id}")
        ->assertOk()
        ->assertJsonPath('data.id', $id);
    $this->patchJson("/api/v1/organization-units/{$id}", [
        'name' => 'Khối sản phẩm',
        'code' => 'PRODUCT',
        'is_active' => true,
    ])->assertOk()->assertJsonPath('data.code', 'PRODUCT');
    $this->deleteJson("/api/v1/organization-units/{$id}")->assertOk();

    expect(OrganizationUnit::find($id))->toBeNull();
});

test('user api supports crud status changes and revokes sessions when disabled', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = userWithPermissions([
        PermissionName::UserView->value,
        PermissionName::UserCreate->value,
        PermissionName::UserUpdate->value,
        PermissionName::UserDisable->value,
        PermissionName::UserAssignRole->value,
    ]);
    $unit = OrganizationUnit::factory()->create();
    Sanctum::actingAs($admin);

    $created = $this->postJson('/api/v1/users', [
        'organization_unit_id' => $unit->id,
        'name' => 'Mobile User',
        'email' => 'mobile@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roles' => [RoleName::Employee->value],
    ])->assertCreated();
    $id = $created->json('data.id');
    $user = User::findOrFail($id);
    app(ApiTokenService::class)->issue($user, 'phone');

    $this->getJson('/api/v1/users?search=mobile@example.com')
        ->assertOk()
        ->assertJsonCount(1, 'data');
    $this->patchJson("/api/v1/users/{$id}", [
        'organization_unit_id' => $unit->id,
        'name' => 'Mobile User Updated',
        'email' => 'mobile@example.com',
        'roles' => [RoleName::Employee->value],
    ])->assertOk()->assertJsonPath('data.name', 'Mobile User Updated');
    $this->patchJson("/api/v1/users/{$id}/disable")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($user->tokens()->count())->toBe(0)
        ->and(ApiRefreshSession::where('user_id', $id)->whereNull('revoked_at')->count())->toBe(0);

    $this->patchJson("/api/v1/users/{$id}/enable")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
    $this->deleteJson("/api/v1/users/{$id}")->assertOk();
    expect(User::find($id))->toBeNull();
});

test('permission matrix api exposes and updates role permissions', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = userWithPermissions([PermissionName::SystemManageSettings->value]);
    Sanctum::actingAs($admin);
    $role = Role::findByName(RoleName::Employee->value, 'web');
    $permissions = $role->permissions->pluck('name')->all();
    $updated = array_values(array_unique([...$permissions, PermissionName::TaskExport->value]));

    $this->getJson('/api/v1/permission-matrix')
        ->assertOk()
        ->assertJsonStructure(['data' => ['roles', 'permission_groups', 'matrix', 'locked_roles']]);
    $this->putJson('/api/v1/permission-matrix', [
        'matrix' => [RoleName::Employee->value => $updated],
    ])->assertOk()->assertJsonPath('data.changed_roles', 1);

    expect($role->fresh()->hasPermissionTo(PermissionName::TaskExport->value))->toBeTrue();
});

test('audit log api is paginated filterable and protected by permission', function () {
    $viewer = userWithPermissions([PermissionName::SystemViewAuditLogs->value]);
    $other = User::factory()->create(['email' => 'audited@example.com']);
    AuditLog::create([
        'actor_id' => $other->id,
        'action' => AuditAction::UserUpdated->value,
        'subject_type' => User::class,
        'subject_id' => $other->id,
        'created_at' => now(),
    ]);
    Sanctum::actingAs($viewer);

    $this->getJson('/api/v1/audit-logs?search=audited@example.com&action='.AuditAction::UserUpdated->value)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.actor.email', 'audited@example.com');

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/audit-logs')->assertForbidden();
});
