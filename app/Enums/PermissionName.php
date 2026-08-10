<?php

namespace App\Enums;

enum PermissionName: string
{
    case OrganizationView = 'organization.view';
    case OrganizationCreate = 'organization.create';
    case OrganizationUpdate = 'organization.update';
    case OrganizationDelete = 'organization.delete';
    case OrganizationManageMembers = 'organization.manage_members';

    case UserView = 'user.view';
    case UserCreate = 'user.create';
    case UserUpdate = 'user.update';
    case UserDisable = 'user.disable';
    case UserAssignRole = 'user.assign_role';

    case TaskView = 'task.view';
    case TaskCreate = 'task.create';
    case TaskUpdate = 'task.update';
    case TaskDelete = 'task.delete';
    case TaskAssign = 'task.assign';
    case TaskComment = 'task.comment';
    case TaskSubmit = 'task.submit';
    case TaskApprove = 'task.approve';
    case TaskReject = 'task.reject';
    case TaskExport = 'task.export';
    case TaskViewOwn = 'task.view_own';
    case TaskViewDepartment = 'task.view_department';
    case TaskViewAll = 'task.view_all';

    case ProjectView = 'project.view';
    case ProjectCreate = 'project.create';
    case ProjectUpdate = 'project.update';
    case ProjectDelete = 'project.delete';
    case ProjectManageMembers = 'project.manage_members';
    case ProjectClose = 'project.close';
    case ProjectExport = 'project.export';
    case ProjectViewOwn = 'project.view_own';
    case ProjectViewDepartment = 'project.view_department';
    case ProjectViewAll = 'project.view_all';

    case ReportViewOwn = 'report.view_own';
    case ReportViewDepartment = 'report.view_department';
    case ReportViewAll = 'report.view_all';
    case ReportExport = 'report.export';

    case SystemManageSettings = 'system.manage_settings';
    case SystemViewAuditLogs = 'system.view_audit_logs';

    public function label(): string
    {
        return match ($this) {
            self::OrganizationView => 'Xem đơn vị',
            self::OrganizationCreate => 'Tạo đơn vị',
            self::OrganizationUpdate => 'Cập nhật đơn vị',
            self::OrganizationDelete => 'Xoá đơn vị',
            self::OrganizationManageMembers => 'Quản lý thành viên đơn vị',

            self::UserView => 'Xem người dùng',
            self::UserCreate => 'Tạo người dùng',
            self::UserUpdate => 'Cập nhật người dùng',
            self::UserDisable => 'Vô hiệu hoá người dùng',
            self::UserAssignRole => 'Gán vai trò người dùng',

            self::TaskView => 'Xem công việc',
            self::TaskCreate => 'Tạo công việc',
            self::TaskUpdate => 'Cập nhật công việc',
            self::TaskDelete => 'Xoá công việc',
            self::TaskAssign => 'Giao công việc',
            self::TaskComment => 'Bình luận công việc',
            self::TaskSubmit => 'Nộp công việc',
            self::TaskApprove => 'Duyệt công việc',
            self::TaskReject => 'Từ chối công việc',
            self::TaskExport => 'Xuất công việc',
            self::TaskViewOwn => 'Xem công việc của tôi',
            self::TaskViewDepartment => 'Xem công việc trong đơn vị',
            self::TaskViewAll => 'Xem toàn bộ công việc',

            self::ProjectView => 'Xem việc dự án',
            self::ProjectCreate => 'Tạo việc dự án',
            self::ProjectUpdate => 'Cập nhật việc dự án',
            self::ProjectDelete => 'Xoá việc dự án',
            self::ProjectManageMembers => 'Quản lý thành viên việc dự án',
            self::ProjectClose => 'Đóng việc dự án',
            self::ProjectExport => 'Xuất việc dự án',
            self::ProjectViewOwn => 'Xem việc dự án của tôi',
            self::ProjectViewDepartment => 'Xem việc dự án trong đơn vị',
            self::ProjectViewAll => 'Xem toàn bộ việc dự án',

            self::ReportViewOwn => 'Xem báo cáo của tôi',
            self::ReportViewDepartment => 'Xem báo cáo trong đơn vị',
            self::ReportViewAll => 'Xem toàn bộ báo cáo',
            self::ReportExport => 'Xuất báo cáo',

            self::SystemManageSettings => 'Quản lý cài đặt hệ thống',
            self::SystemViewAuditLogs => 'Xem nhật ký kiểm toán',
        };
    }

    /**
     * Tiền tố module dùng để nhóm permission trên màn hình ma trận phân quyền.
     */
    public function module(): string
    {
        return explode('.', $this->value)[0];
    }
}
