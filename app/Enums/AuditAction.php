<?php

namespace App\Enums;

enum AuditAction: string
{
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserRolesUpdated = 'user.roles_updated';
    case UserDisabled = 'user.disabled';
    case UserEnabled = 'user.enabled';
    case UserDeleted = 'user.deleted';
    case OrganizationUnitDeleted = 'organization_unit.deleted';
    case TaskDeleted = 'task.deleted';
    case TaskAttachmentUploaded = 'task.attachment_uploaded';
    case TaskAttachmentDeleted = 'task.attachment_deleted';
    case ProjectDeleted = 'project.deleted';
    case ProjectClosed = 'project.closed';
    case ProjectClosedWithException = 'project.closed_with_exception';
    case ProjectMemberAdded = 'project.member_added';
    case ProjectMemberRoleUpdated = 'project.member_role_updated';
    case ProjectMemberRemoved = 'project.member_removed';
}
