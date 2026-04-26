<?php

namespace App\Enums;

enum ReconciliationStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FLAGGED = 'flagged';

    public function label(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::FLAGGED => 'Flagged for Review',
        };
    }
}
