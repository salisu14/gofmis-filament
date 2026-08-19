<?php

namespace App\Enums;

enum WidowLoanRecoveryStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case PROMISE_TO_PAY = 'promise_to_pay';
    case UNDER_HARDSHIP_REVIEW = 'under_hardship_review';
    case RESTRUCTURED = 'restructured';
    case ESCALATED = 'escalated';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';
    case REFERRED_FOR_WRITE_OFF = 'referred_for_write_off';

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::IN_PROGRESS => 'In Progress',
            self::PROMISE_TO_PAY => 'Promise to Pay',
            self::UNDER_HARDSHIP_REVIEW => 'Under Hardship Review',
            self::RESTRUCTURED => 'Restructured',
            self::ESCALATED => 'Escalated',
            self::RESOLVED => 'Resolved',
            self::CLOSED => 'Closed',
            self::REFERRED_FOR_WRITE_OFF => 'Referred for Write-Off',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OPEN => 'warning',
            self::IN_PROGRESS => 'info',
            self::PROMISE_TO_PAY => 'primary',
            self::UNDER_HARDSHIP_REVIEW => 'warning',
            self::RESTRUCTURED => 'info',
            self::ESCALATED => 'danger',
            self::RESOLVED => 'success',
            self::CLOSED => 'gray',
            self::REFERRED_FOR_WRITE_OFF => 'danger',
        };
    }
}
