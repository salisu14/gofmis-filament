<?php

namespace App\Enums;

enum WelfarePackageStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::OPEN => 'Open',
            self::CLOSED => 'Closed',
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return match($this) {
            self::DRAFT => in_array($status, [self::OPEN, self::CLOSED]),
            self::OPEN => in_array($status, [self::CLOSED, self::DRAFT]),
            self::CLOSED => in_array($status, [self::OPEN, self::DRAFT]),
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::OPEN => 'success',
            self::CLOSED => 'danger',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::DRAFT => 'heroicon-o-pencil',
            self::OPEN => 'heroicon-o-check-circle',
            self::CLOSED => 'heroicon-o-lock-closed',
        };
    }
}
