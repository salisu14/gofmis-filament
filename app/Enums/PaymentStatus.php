<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING   => 'Pending',
            self::COMPLETED => 'Completed',
            self::FAILED    => 'Failed',
            self::REFUNDED  => 'Refunded',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING   => 'warning',  // Orange
            self::COMPLETED => 'success',  // Green
            self::FAILED    => 'danger',   // Red
            self::REFUNDED  => 'gray',     // Gray
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING   => 'heroicon-o-clock',
            self::COMPLETED => 'heroicon-o-check-badge',
            self::FAILED    => 'heroicon-o-x-circle',
            self::REFUNDED  => 'heroicon-o-queue-list',
        };
    }
}
