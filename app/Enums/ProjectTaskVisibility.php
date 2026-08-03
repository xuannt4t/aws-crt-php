<?php

namespace App\Enums;

enum ProjectTaskVisibility: string
{
    case Own = 'own';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Own => 'Chỉ việc của mình',
            self::All => 'Toàn bộ việc dự án',
        };
    }
}
