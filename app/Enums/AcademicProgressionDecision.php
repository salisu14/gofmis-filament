<?php

namespace App\Enums;

enum AcademicProgressionDecision: string
{
    case PROMOTED = 'promoted';
    case REPEATED = 'repeated';
    case DEMOTED = 'demoted';
    case GRADUATED = 'graduated';
    case TRANSFERRED = 'transferred';
    case WITHDRAWN = 'withdrawn';
    case DROPPED_OUT = 'dropped_out';

    public function label(): string
    {
        return match ($this) {
            self::PROMOTED => 'Promoted',
            self::REPEATED => 'Repeated / Retained',
            self::DEMOTED => 'Demoted',
            self::GRADUATED => 'Graduated / Completed',
            self::TRANSFERRED => 'Transferred School',
            self::WITHDRAWN => 'Withdrawn (Administrative Exit)',
            self::DROPPED_OUT => 'Dropped Out (Discontinued)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PROMOTED => 'success',
            self::REPEATED => 'warning',
            self::DEMOTED => 'danger',
            self::GRADUATED => 'info',
            self::TRANSFERRED => 'gray',
            self::WITHDRAWN => 'warning',
            self::DROPPED_OUT => 'danger',
        };
    }
}
