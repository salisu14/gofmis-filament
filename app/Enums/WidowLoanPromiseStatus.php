<?php

namespace App\Enums;

enum WidowLoanPromiseStatus: string
{
    case OPEN = 'open';
    case FULFILLED = 'fulfilled';
    case PARTIALLY_FULFILLED = 'partially_fulfilled';
    case BROKEN = 'broken';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::FULFILLED => 'Fulfilled',
            self::PARTIALLY_FULFILLED => 'Partially Fulfilled',
            self::BROKEN => 'Broken',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OPEN => 'warning',
            self::FULFILLED => 'success',
            self::PARTIALLY_FULFILLED => 'info',
            self::BROKEN => 'danger',
            self::CANCELLED => 'gray',
        };
    }
}
