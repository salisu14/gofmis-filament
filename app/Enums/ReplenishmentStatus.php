<?php

namespace App\Enums;

enum ReplenishmentStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PROCESSED = 'processed';

    public function isEditable(): bool
    {
        return $this === self::DRAFT;
    }
}
