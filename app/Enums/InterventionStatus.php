<?php

namespace App\Enums;

enum InterventionStatus: string
{
    case PENDING = 'pending';
    case UNDER_REVIEW = 'under_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case FULFILLED = 'fulfilled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING       => 'Pending',
            self::UNDER_REVIEW  => 'Under Review',
            self::APPROVED      => 'Approved',
            self::REJECTED      => 'Rejected',
            self::CANCELLED     => 'Cancelled',
            self::FULFILLED     => 'Fulfilled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING       => 'gray',
            self::UNDER_REVIEW  => 'info',     // Blue
            self::APPROVED      => 'success',  // Green
            self::REJECTED      => 'danger',   // Red
            self::CANCELLED     => 'gray',
            self::FULFILLED     => 'primary',  // Purple/Brand color
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING       => 'heroicon-o-clock',
            self::UNDER_REVIEW  => 'heroicon-o-magnifying-glass',
            self::APPROVED      => 'heroicon-o-check-circle',
            self::REJECTED      => 'heroicon-o-x-circle',
            self::CANCELLED     => 'heroicon-o-no-symbol',
            self::FULFILLED     => 'heroicon-o-gift',
        };
    }
}
