<?php

namespace App\Enums;

enum TaskContext: string
{
    case Overview = 'overview';
    case Department = 'department';

    public function label(): string
    {
        return match ($this) {
            self::Overview => 'Tổng quan việc',
            self::Department => 'Việc phòng ban',
        };
    }
}
