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

    case ProjectView = 'project.view';
    case ProjectCreate = 'project.create';
    case ProjectUpdate = 'project.update';
    case ProjectDelete = 'project.delete';
    case ProjectManageMembers = 'project.manage_members';
    case ProjectClose = 'project.close';
    case ProjectExport = 'project.export';

    case ReportViewOwn = 'report.view_own';
    case ReportViewDepartment = 'report.view_department';
    case ReportViewAll = 'report.view_all';
    case ReportExport = 'report.export';

    case SystemManageSettings = 'system.manage_settings';
    case SystemViewAuditLogs = 'system.view_audit_logs';
}
