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

    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin => 'Quản trị hệ thống',
            self::Director => 'Giám đốc',
            self::DepartmentManager => 'Quản lý phòng ban',
            self::ProjectManager => 'Quản lý dự án',
            self::Employee => 'Nhân viên',
            self::Auditor => 'Kiểm toán viên',
        };
    }
}
