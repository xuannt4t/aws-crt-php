<?php

namespace App\Enums;

enum ProjectMemberRole: string
{
    case Manager = 'manager';
    case Member = 'member';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Quản lý việc dự án',
            self::Member => 'Thành viên',
            self::Viewer => 'Người theo dõi',
        };
    }
}
