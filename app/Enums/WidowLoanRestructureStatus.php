<?php

namespace App\Enums;

enum WidowLoanRestructureStatus: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case APPLIED = 'applied';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::APPLIED => 'Applied',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING_APPROVAL => 'warning',
            self::APPROVED => 'info',
            self::REJECTED => 'danger',
            self::APPLIED => 'success',
            self::CANCELLED => 'gray',
        };
    }
}
