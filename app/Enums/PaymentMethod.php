<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case BANK = 'bank';
    case TRANSFER = 'transfer';
    case CHECK = 'check';
    case DIGITAL = 'digital';

    public function getLabel(): string
    {
        return match ($this) {
            self::CASH     => 'Cash',
            self::BANK     => 'Bank Deposit',
            self::TRANSFER => 'Transfer',
            self::DIGITAL  => 'Digital Payment',
            self::CHECK    => 'Check',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::CASH     => 'success', // Green
            self::BANK     => 'info',    // Blue
            self::TRANSFER => 'warning', // Orange
            self::DIGITAL  => 'primary',
            self::CHECK    => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::CASH     => 'heroicon-o-banknotes',
            self::BANK     => 'heroicon-o-building-library',
            self::TRANSFER => 'heroicon-o-arrow-path',
            self::DIGITAL  => 'heroicon-o-credit-card',
            self::CHECK    => 'heroicon-o-clipboard-document-list',
        };
    }
}
