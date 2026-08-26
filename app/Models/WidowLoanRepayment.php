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
        'amount' => 'decimal:2',
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

    /**
     * Cumulative amount paid strictly up to and including this repayment,
     * ordered deterministically as (paid_at ASC, receipt_number ASC).
     *
     * `receipt_number` is auto-assigned from `MAX(receipt_number) + 1` at
     * posting time (see WidowLoanService::recordRepayment), so it is a stable
     * monotonic business sequence. Using it as the tie-breaker removes the
     * ambiguity that second-precision `created_at` (plus random UUID `id`) would
     * otherwise introduce when several repayments share the same paid_at date.
     *
     * Legacy rows with a NULL receipt_number are ordered as the earliest
     * (COALESCE -> 0) so historical reprints remain self-consistent.
     */
    public function getTotalPaidUpToThisAttribute(): float
    {
        if (! $this->widowLoan) {
            return 0;
        }

        return (float) $this->widowLoan->repayments()
            ->where(function ($query) {
                $query->where('paid_at', '<', $this->paid_at)
                    ->orWhere(function ($q) {
                        $q->where('paid_at', $this->paid_at)
                            ->whereRaw('COALESCE(receipt_number, 0) < ?', [(int) ($this->receipt_number ?? 0)]);
                    })
                    ->orWhere('id', $this->id);
            })
            ->sum('amount');
    }

    public function getBalanceAfterAttribute(): float
    {
        if (! $this->widowLoan) {
            return 0;
        }

        $totalPayable = (float) $this->widowLoan->total_payable;

        return max(0, $totalPayable - $this->total_paid_up_to_this);
    }

    public function getInstallmentContext(): array
    {
        if (! $this->widowLoan) {
            return ['n' => 1, 'm' => 1];
        }

        $schedules = $this->widowLoan->schedules()
            ->whereNull('superseded_at')
            ->orderBy('installment_number')
            ->get();

        $m = max(1, $schedules->count());
        $totalPaidUpToThis = $this->total_paid_up_to_this;

        $requiredTotal = 0;
        $n = 1;

        foreach ($schedules as $schedule) {
            $requiredTotal += (float) $schedule->amount_due;
            $n = $schedule->installment_number;
            // If the total paid so far is less than or equal to the required total for THIS schedule,
            // then THIS is the active installment we are paying towards.
            if ($totalPaidUpToThis <= $requiredTotal) {
                break;
            }
        }

        return ['n' => min($n, $m), 'm' => $m];
    }

    protected static function booted(): void
    {
        // If a repayment is updated, ensure the parent loan recalculates
        static::updated(function (WidowLoanRepayment $repayment) {
            $repayment->widowLoan->refreshBalance();
        });

        // If a repayment is deleted, ensure the parent loan recalculates
        static::deleted(function (WidowLoanRepayment $repayment) {
            $repayment->widowLoan->refreshBalance();
        });

        // Prevent modification/deletion of existing/posted repayments. This is
        // deliberately UNCONDITIONAL: the previous console/unit-test carve-out
        // evaluated to false in CLI, cron, queue and test contexts, silently
        // disabling the immutability guard exactly where automated processing
        // runs. The only legitimate post-write to a repayment is attaching its
        // transaction reference, which uses updateQuietly (fires no model
        // events) and is scoped in attachTransactionReference().
        static::updating(function (WidowLoanRepayment $repayment) {
            throw new \RuntimeException('Posted financial repayments cannot be edited.');
        });

        static::deleting(function (WidowLoanRepayment $repayment) {
            throw new \RuntimeException('Posted financial repayments cannot be deleted.');
        });
    }

    /**
     * Narrowly scoped domain method to attach a system transaction reference
     * after a repayment is posted, without bypassing the general financial
     * immutability constraints.
     *
     * Guarantees:
     *  - accepts ONLY the transaction_id (no arbitrary attributes);
     *  - idempotent when the same reference is already attached;
     *  - refuses to overwrite a different existing reference;
     *  - writes via updateQuietly purely to avoid a redundant refreshBalance()
     *    (the transaction is itself created inside the same posting transaction
     *    so the FK is a pure reference, incapable of altering amount/paid_at/
     *    loan/bank/method/receipt or any balance fact).
     *
     * @throws \RuntimeException when an overwrite of a different reference is attempted.
     */
    public function attachTransactionReference($transactionId): self
    {
        $existing = $this->getOriginal('transaction_id');

        if ($existing !== null && $existing !== $transactionId) {
            throw new \RuntimeException('Cannot overwrite an existing transaction reference on a posted repayment.');
        }

        if ($existing === $transactionId) {
            return $this;
        }

        $this->updateQuietly(['transaction_id' => $transactionId]);
        $this->syncOriginal();

        return $this;
    }
}
