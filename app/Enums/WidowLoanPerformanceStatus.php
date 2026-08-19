<?php

namespace App\Enums;

enum WidowLoanPerformanceStatus: string
{
    case CURRENT = 'current';
    case OVERDUE = 'overdue';
    case DELINQUENT = 'delinquent';
    case HARDSHIP = 'hardship';
    case RESTRUCTURED = 'restructured';
    case DEFAULTED = 'defaulted';
    case WRITTEN_OFF = 'written_off';

    public function getLabel(): string
    {
        return match ($this) {
            self::CURRENT => 'Current',
            self::OVERDUE => 'Overdue',
            self::DELINQUENT => 'Delinquent',
            self::HARDSHIP => 'Hardship',
            self::RESTRUCTURED => 'Restructured',
            self::DEFAULTED => 'Defaulted',
            self::WRITTEN_OFF => 'Written Off',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::CURRENT => 'success',
            self::OVERDUE => 'warning',
            self::DELINQUENT => 'danger',
            self::HARDSHIP => 'info',
            self::RESTRUCTURED => 'primary',
            self::DEFAULTED => 'danger',
            self::WRITTEN_OFF => 'gray',
        };
    }
}
