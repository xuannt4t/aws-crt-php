<?php

namespace App\Enums;

enum RoleName: string
{
    case SystemAdmin = 'system_admin';
    case Director = 'director';
    case DepartmentManager = 'department_manager';
    case ProjectManager = 'project_manager';
    case Employee = 'employee';
    case Auditor = 'auditor';
}
