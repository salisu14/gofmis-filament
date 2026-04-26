<?php

namespace App\Enums;

enum TransactionCategory: string
{
    case SUPPLIES = 'supplies';
    case TRANSPORT = 'transport';
    case MEDICAL = 'medical';
    case FUNERAL_SERVICES = 'funeral_services';
    case ADMINISTRATIVE = 'administrative';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SUPPLIES => 'Supplies',
            self::TRANSPORT => 'Transport',
            self::MEDICAL => 'Medical',
            self::FUNERAL_SERVICES => 'Funeral Services',
            self::ADMINISTRATIVE => 'Administrative',
            self::OTHER => 'Other',
        };
    }
}
