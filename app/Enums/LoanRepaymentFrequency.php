<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum LoanRepaymentFrequency: string implements HasLabel, HasColor, HasIcon
{
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::WEEKLY => 'Weekly',
            self::MONTHLY => 'Monthly',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::WEEKLY => 'info',
            self::MONTHLY => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::WEEKLY => 'heroicon-m-calendar',
            self::MONTHLY => 'heroicon-m-calendar-days',
        };
    }
}
