<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PrescriptionStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case TREATED = 'treated';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Treatment',
            self::TREATED => 'Treated / Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::TREATED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::TREATED => 'heroicon-o-check-badge',
            self::CANCELLED => 'heroicon-o-x-circle',
        };
    }
}
