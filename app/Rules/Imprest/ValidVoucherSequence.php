<?php

namespace App\Rules\Imprest;

use App\Models\ImprestTransaction;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidVoucherSequence implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $lastVoucher = ImprestTransaction::withTrashed()
            ->where('voucher_no', '<', $value)
            ->orderBy('voucher_no', 'desc')
            ->value('voucher_no');

        if ($lastVoucher && $this->extractSequence($value) !== $this->extractSequence($lastVoucher) + 1) {
            $fail('Voucher sequence gap detected. Expected next voucher after ' . $lastVoucher);
        }
    }

    private function extractSequence(string $voucherNo): int
    {
        return (int) substr($voucherNo, -4);
    }
}
