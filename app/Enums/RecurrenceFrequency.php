<?php

namespace App\Enums;

enum RecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Hằng ngày',
            self::Weekly => 'Hằng tuần',
            self::Monthly => 'Hằng tháng',
            self::Quarterly => 'Hằng quý',
        };
    }
}
