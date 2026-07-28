<?php

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('role permission seeder creates the complete permission matrix', function () {
    $this->seed(RolePermissionSeeder::class);

    $values = static fn (array $permissions): array => collect($permissions)
        ->map(static fn (PermissionName $permission): string => $permission->value)
        ->sort()
        ->values()
        ->all();

    $expected = [
        RoleName::SystemAdmin->value => $values(PermissionName::cases()),
        RoleName::Director->value => $values([
            PermissionName::OrganizationView,
            PermissionName::OrganizationCreate,
            PermissionName::OrganizationUpdate,
            PermissionName::OrganizationManageMembers,
            PermissionName::UserView,
            PermissionName::UserCreate,
            PermissionName::UserUpdate,
            PermissionName::UserDisable,
            PermissionName::TaskView,
            PermissionName::TaskCreate,
            PermissionName::TaskUpdate,
            PermissionName::TaskDelete,
            PermissionName::TaskAssign,
            PermissionName::TaskComment,
            PermissionName::TaskSubmit,
            PermissionName::TaskApprove,
            PermissionName::TaskReject,
            PermissionName::TaskExport,
            PermissionName::ProjectView,
            PermissionName::ProjectCreate,
            PermissionName::ProjectUpdate,
            PermissionName::ProjectDelete,
            PermissionName::ProjectManageMembers,
            PermissionName::ProjectClose,
            PermissionName::ProjectExport,
            PermissionName::ReportViewOwn,
            PermissionName::ReportViewDepartment,
            PermissionName::ReportViewAll,
            PermissionName::ReportExport,
            PermissionName::SystemViewAuditLogs,
        ]),
        RoleName::DepartmentManager->value => $values([
            PermissionName::OrganizationView,
            PermissionName::OrganizationManageMembers,
            PermissionName::UserView,
            PermissionName::TaskView,
            PermissionName::TaskCreate,
            PermissionName::TaskUpdate,
            PermissionName::TaskAssign,
            PermissionName::TaskComment,
            PermissionName::TaskSubmit,
            PermissionName::TaskApprove,
            PermissionName::TaskReject,
            PermissionName::ProjectView,
            PermissionName::ReportViewOwn,
            PermissionName::ReportViewDepartment,
        ]),
        RoleName::ProjectManager->value => $values([
            PermissionName::OrganizationView,
            PermissionName::UserView,
            PermissionName::TaskView,
            PermissionName::TaskCreate,
            PermissionName::TaskUpdate,
            PermissionName::TaskDelete,
            PermissionName::TaskAssign,
            PermissionName::TaskComment,
            PermissionName::TaskSubmit,
            PermissionName::TaskExport,
            PermissionName::ProjectView,
            PermissionName::ProjectCreate,
            PermissionName::ProjectUpdate,
            PermissionName::ProjectDelete,
            PermissionName::ProjectManageMembers,
            PermissionName::ProjectClose,
            PermissionName::ProjectExport,
            PermissionName::ReportViewOwn,
        ]),
        RoleName::Employee->value => $values([
            PermissionName::OrganizationView,
            PermissionName::UserView,
            PermissionName::TaskView,
            PermissionName::TaskCreate,
            PermissionName::TaskUpdate,
            PermissionName::TaskComment,
            PermissionName::TaskSubmit,
            PermissionName::ProjectView,
            PermissionName::ReportViewOwn,
        ]),
        RoleName::Auditor->value => $values([
            PermissionName::OrganizationView,
            PermissionName::UserView,
            PermissionName::TaskView,
            PermissionName::TaskExport,
            PermissionName::ProjectView,
            PermissionName::ProjectExport,
            PermissionName::ReportViewAll,
            PermissionName::ReportExport,
            PermissionName::SystemViewAuditLogs,
        ]),
    ];

    expect(Permission::query()->count())->toBe(count(PermissionName::cases()))
        ->and(Role::query()->count())->toBe(count(RoleName::cases()));

    foreach ($expected as $roleName => $permissions) {
        $actual = Role::findByName($roleName, 'web')
            ->permissions
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        expect($actual)->toBe($permissions);
    }
});

test('role permission seeder is idempotent and migrates legacy system admins', function () {
    $legacyAdmin = User::factory()->create(['is_system_admin' => true]);

    $this->seed(RolePermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    expect(Permission::query()->count())->toBe(count(PermissionName::cases()))
        ->and(Role::query()->count())->toBe(count(RoleName::cases()))
        ->and($legacyAdmin->fresh()->hasRole(RoleName::SystemAdmin->value))->toBeTrue()
        ->and($legacyAdmin->fresh()->getAllPermissions()->count())->toBe(count(PermissionName::cases()));
});
