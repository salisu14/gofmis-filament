<?php
// app/Enums/ProjectStatus.php

namespace App\Enums;

enum ProjectStatus: string
{
    case PLANNING = 'planning';
    case APPROVED = 'approved';
    case IN_PROGRESS = 'in_progress';
    case ON_HOLD = 'on_hold';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::PLANNING => 'Planning',
            self::APPROVED => 'Approved',
            self::IN_PROGRESS => 'In Progress',
            self::ON_HOLD => 'On Hold',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PLANNING => 'gray',
            self::APPROVED => 'info',
            self::IN_PROGRESS => 'warning',
            self::ON_HOLD => 'danger',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
