<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'CASH';
    case BANK = 'BANK';
    case TRANSFER = 'TRANSFER';

    public function getLabel(): string
    {
        return match ($this) {
            self::CASH     => 'Cash',
            self::BANK     => 'Bank Deposit',
            self::TRANSFER => 'Transfer',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::CASH     => 'success', // Green
            self::BANK     => 'info',    // Blue
            self::TRANSFER => 'warning', // Orange
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::CASH     => 'heroicon-o-banknotes',
            self::BANK     => 'heroicon-o-building-library',
            self::TRANSFER => 'heroicon-o-arrow-path',
        };
    }
}
