<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WidowLoanRepayment extends Model
{
    use HasUuids;

    protected $table = 'widow_loan_repayments';

    protected $fillable = [
        'widow_loan_id',
        'bank_account_id',
        'receipt_number',
        'amount',
        'paid_at',
        'payment_method',
        'transaction_id',
        'notes',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | NOTE: We intentionally do NOT call refreshBalance() here.
    |
    | Balance recalculation is handled atomically inside WidowLoanService
    | after every repayment is persisted. Calling refreshBalance() here
    | would cause a double-update when the service already updates the totals.
    |--------------------------------------------------------------------------
    */

    // ==================================================
    // Relationships
    // ==================================================

    public function widowLoan(): BelongsTo
    {
        return $this->belongsTo(WidowLoan::class, 'widow_loan_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function getBalanceAfterAttribute(): float
    {
        // Safety check in case relationship isn't loaded
        if (!$this->widowLoan) {
            return 0;
        }

        $totalPayable = (float) $this->widowLoan->total_payable;

        // Sum all repayments made up to and including this one's date/time
        $totalPaidUpToThis = $this->widowLoan->repayments()
            ->where('paid_at', '<=', $this->paid_at)
            ->where('created_at', '<=', $this->created_at)
            ->sum('amount');

        return max(0, $totalPayable - (float) $totalPaidUpToThis);
    }
}
