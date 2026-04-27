<?php

namespace App\Enums;

enum CollectionStatus: string
{
    case NOT_COLLECTED = 'not_collected';
    case COLLECTED = 'collected';

    public function label(): string
    {
        return match($this) {
            self::NOT_COLLECTED => 'Not Collected',
            self::COLLECTED => 'Collected',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NOT_COLLECTED => 'warning',
            self::COLLECTED => 'success',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::NOT_COLLECTED => 'heroicon-o-inbox',
            self::COLLECTED => 'heroicon-o-check-badge',
        };
    }
}
