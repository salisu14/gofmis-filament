<?php

namespace App\Enums;

enum FundStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::CLOSED => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::SUSPENDED => 'warning',
            self::CLOSED => 'danger',
        };
    }
}
