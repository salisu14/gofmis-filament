<?php

namespace App\Enums;

enum WidowLoanRecoveryActivityType: string
{
    case CALL = 'call';
    case VISIT = 'visit';
    case SMS = 'sms';
    case MEETING = 'meeting';
    case PROMISE_TO_PAY = 'promise_to_pay';
    case PAYMENT_FOLLOW_UP = 'payment_follow_up';
    case HARDSHIP_REFERRAL = 'hardship_referral';
    case RESTRUCTURE_DISCUSSION = 'restructure_discussion';
    case ESCALATION = 'escalation';
    case WRITE_OFF_RECOMMENDATION = 'write_off_recommendation';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::CALL => 'Phone Call',
            self::VISIT => 'Field Visit',
            self::SMS => 'SMS Notification',
            self::MEETING => 'In-Person Meeting',
            self::PROMISE_TO_PAY => 'Recorded Promise to Pay',
            self::PAYMENT_FOLLOW_UP => 'Payment Follow Up',
            self::HARDSHIP_REFERRAL => 'Hardship Referral',
            self::RESTRUCTURE_DISCUSSION => 'Restructuring Discussion',
            self::ESCALATION => 'Escalated Action',
            self::WRITE_OFF_RECOMMENDATION => 'Recommended for Write-Off',
            self::OTHER => 'Other Action',
        };
    }
}
