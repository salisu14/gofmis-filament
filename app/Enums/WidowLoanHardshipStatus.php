<?php

namespace App\Enums;

enum WidowLoanHardshipStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case VERIFIED = 'verified';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CLOSED = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Review',
            self::UNDER_REVIEW => 'Under Review',
            self::VERIFIED => 'Verified',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::CLOSED => 'Closed',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::UNDER_REVIEW => 'info',
            self::VERIFIED => 'primary',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::CLOSED => 'gray',
        };
    }
}
