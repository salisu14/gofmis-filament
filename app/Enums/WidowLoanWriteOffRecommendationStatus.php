<?php

namespace App\Enums;

enum WidowLoanWriteOffRecommendationStatus: string
{
    case PENDING = 'pending';
    case ENDORSED = 'endorsed';
    case REJECTED = 'rejected';
    case EXECUTED = 'executed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Review',
            self::ENDORSED => 'Endorsed',
            self::REJECTED => 'Rejected',
            self::EXECUTED => 'Executed / Written Off',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::ENDORSED => 'info',
            self::REJECTED => 'danger',
            self::EXECUTED => 'success',
        };
    }
}
