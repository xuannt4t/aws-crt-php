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
            PermissionName::TaskViewAll,
            PermissionName::ProjectViewAll,
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
            PermissionName::TaskViewDepartment,
            PermissionName::ProjectViewDepartment,
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
            PermissionName::TaskViewOwn,
            PermissionName::ProjectViewOwn,
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
            PermissionName::TaskViewOwn,
            PermissionName::ProjectViewOwn,
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
            PermissionName::TaskViewAll,
            PermissionName::ProjectViewAll,
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

test('role permission seeder does not overwrite a role that already has permissions configured manually', function () {
    $this->seed(RolePermissionSeeder::class);

    $employeeRole = Role::findByName(RoleName::Employee->value, 'web');
    $employeeRole->syncPermissions([PermissionName::TaskView->value]);

    $this->seed(RolePermissionSeeder::class);

    $actual = Role::findByName(RoleName::Employee->value, 'web')
        ->permissions
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($actual)->toBe([PermissionName::TaskView->value]);
});

test('role permission seeder assigns baseline scope permissions for a brand new role', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(
        Role::findByName(RoleName::DepartmentManager->value, 'web')
            ->permissions
            ->pluck('name')
            ->all()
    )->toContain(PermissionName::TaskViewDepartment->value, PermissionName::ProjectViewDepartment->value)
        ->and(
            Role::findByName(RoleName::ProjectManager->value, 'web')
                ->permissions
                ->pluck('name')
                ->all()
        )->toContain(PermissionName::TaskViewOwn->value, PermissionName::ProjectViewOwn->value)
        ->and(
            Role::findByName(RoleName::Auditor->value, 'web')
                ->permissions
                ->pluck('name')
                ->all()
        )->toContain(PermissionName::TaskViewAll->value, PermissionName::ProjectViewAll->value);
});
