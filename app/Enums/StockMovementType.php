<?php

namespace App\Enums;

enum StockMovementType: string
{
    case OPENING_BALANCE = 'OPENING_BALANCE';
    case PURCHASE_RECEIPT = 'PURCHASE_RECEIPT';
    case DONATION_RECEIPT = 'DONATION_RECEIPT';
    case ADJUSTMENT_IN = 'ADJUSTMENT_IN';
    case ADJUSTMENT_OUT = 'ADJUSTMENT_OUT';
    case WELFARE_ISSUE = 'WELFARE_ISSUE';
    case INTERVENTION_ISSUE = 'INTERVENTION_ISSUE';
    case RETURN_IN = 'RETURN_IN';
    case LOSS_DAMAGE = 'LOSS_DAMAGE';

    public function label(): string
    {
        return match ($this) {
            self::OPENING_BALANCE => 'Opening Balance',
            self::PURCHASE_RECEIPT => 'Purchase Receipt',
            self::DONATION_RECEIPT => 'Donation Receipt',
            self::ADJUSTMENT_IN => 'Stock Adjustment (In)',
            self::ADJUSTMENT_OUT => 'Stock Adjustment (Out)',
            self::WELFARE_ISSUE => 'Welfare Issue / Distribution',
            self::INTERVENTION_ISSUE => 'Intervention Issue',
            self::RETURN_IN => 'Stock Return (In)',
            self::LOSS_DAMAGE => 'Loss / Damage / Expiry',
        };
    }

    public function isInflow(): bool
    {
        return in_array($this, [
            self::OPENING_BALANCE,
            self::PURCHASE_RECEIPT,
            self::DONATION_RECEIPT,
            self::ADJUSTMENT_IN,
            self::RETURN_IN,
        ]);
    }
}
