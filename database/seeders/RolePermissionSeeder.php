<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        foreach ($this->rolePermissions() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            // system_admin luôn giữ TOÀN BỘ permission — đây chính là bất biến
            // mà UpdatePermissionMatrixRequest và màn hình ma trận đang khẳng
            // định. Nếu bỏ qua vai trò này khi nó đã có quyền, mọi permission
            // mới thêm ở bản phát hành sau sẽ không bao giờ được cấp trên bản
            // cài đặt cũ (seeder bỏ qua, ma trận từ chối sửa) trong khi giao
            // diện vẫn hiển thị đã tích. Vì vậy luôn đồng bộ cưỡng bức.
            if ($roleName === RoleName::SystemAdmin->value) {
                $role->syncPermissions($permissions);

                continue;
            }

            if ($role->permissions->isNotEmpty()) {
                continue;
            }

            $role->syncPermissions($permissions);
        }

        User::query()
            ->where('is_system_admin', true)
            ->each(fn (User $user) => $user->assignRole(RoleName::SystemAdmin->value));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<string, list<string>>
     */
    private function rolePermissions(): array
    {
        return [
            RoleName::SystemAdmin->value => array_map(
                static fn (PermissionName $permission): string => $permission->value,
                PermissionName::cases(),
            ),
            RoleName::Director->value => $this->values([
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
            RoleName::DepartmentManager->value => $this->values([
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
            RoleName::ProjectManager->value => $this->values([
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
            RoleName::Employee->value => $this->values([
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
            RoleName::Auditor->value => $this->values([
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
    }

    /**
     * @param  list<PermissionName>  $permissions
     * @return list<string>
     */
    private function values(array $permissions): array
    {
        return array_map(
            static fn (PermissionName $permission): string => $permission->value,
            $permissions,
        );
    }
}
