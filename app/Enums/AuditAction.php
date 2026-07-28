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
}
