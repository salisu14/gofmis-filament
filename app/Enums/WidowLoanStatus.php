<?php

namespace App\Enums;

enum WidowLoanStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case DISBURSED = 'disbursed';
    case COMPLETED = 'completed';
    case DEFAULTED = 'defaulted';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::DISBURSED => 'Disbursed',
            self::COMPLETED => 'Completed',
            self::DEFAULTED => 'Defaulted',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING => 'warning',
            self::APPROVED => 'info',
            self::REJECTED => 'danger',
            self::DISBURSED => 'primary',
            self::COMPLETED => 'success',
            self::DEFAULTED => 'danger',
        };
    }
}
