<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case VOIDED = 'voided';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Approval',
            self::ACTIVE => 'Active',
            self::VOIDED => 'Voided',
            self::REJECTED => 'Rejected',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::VOIDED, self::REJECTED]);
    }
}
