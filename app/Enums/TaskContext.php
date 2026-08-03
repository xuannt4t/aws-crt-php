<?php

namespace App\Enums;

enum TaskContext: string
{
    case Overview = 'overview';
    case Project = 'project';
    case Department = 'department';

    public function label(): string
    {
        return match ($this) {
            self::Overview => 'Tổng quan việc',
            self::Project => 'Việc dự án',
            self::Department => 'Việc phòng ban',
        };
    }
}
