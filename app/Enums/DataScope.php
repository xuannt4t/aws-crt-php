<?php

namespace App\Enums;

enum DataScope: string
{
    case Own = 'own';
    case Department = 'department';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Own => 'Của tôi',
            self::Department => 'Đơn vị',
            self::All => 'Toàn bộ',
        };
    }
}
