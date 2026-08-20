<?php

namespace App\Enums;

enum OrphanStatus: string
{
    case PENDING_REVIEW = 'pending_review';
    case ACTIVE = 'active';
    case REJECTED = 'rejected';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'Pending Review',
            self::ACTIVE => 'Active',
            self::REJECTED => 'Rejected',
            self::ARCHIVED => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'warning',
            self::ACTIVE => 'success',
            self::REJECTED => 'danger',
            self::ARCHIVED => 'gray',
        };
    }
}
