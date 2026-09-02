<?php

namespace App\Enums;

enum AcademicProgressionDecision: string
{
    case PROMOTED = 'promoted';
    case REPEATED = 'repeated';
    case DEMOTED = 'demoted';
    case GRADUATED = 'graduated';
    case TRANSFERRED = 'transferred';

    public function label(): string
    {
        return match ($this) {
            self::PROMOTED => 'Promoted',
            self::REPEATED => 'Repeated / Retained',
            self::DEMOTED => 'Demoted',
            self::GRADUATED => 'Graduated / Completed',
            self::TRANSFERRED => 'Transferred School',
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
        };
    }
}
