<?php

namespace App\Enums;

enum WidowLoanScheduleStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case WAIVED = 'waived';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PAID => 'Paid',
            self::OVERDUE => 'Overdue',
            self::WAIVED => 'Waived / Written Off',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::PAID => 'success',
            self::OVERDUE => 'danger',
            self::WAIVED => 'warning',
        };
    }
}
