<?php

namespace App\Enums;

enum LoanStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PAID = 'paid'; // Fully paid off

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::PAID => 'Fully Paid',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning', // Yellow
            self::APPROVED => 'info',    // Blue
            self::REJECTED => 'danger',  // Red
            self::PAID => 'success', // Green
        };
    }
}
