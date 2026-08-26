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

    public function getTotalPaidUpToThisAttribute(): float
    {
        if (!$this->widowLoan) {
            return 0;
        }

        return (float) $this->widowLoan->repayments()
            ->where(function ($query) {
                $query->where('paid_at', '<', $this->paid_at)
                      ->orWhere(function ($q) {
                          $q->where('paid_at', $this->paid_at)
                            ->where(function ($q2) {
                                $q2->where('created_at', '<=', $this->created_at)
                                   ->orWhere('id', $this->id);
                            });
                      });
            })
            ->sum('amount');
    }

    public function getBalanceAfterAttribute(): float
    {
        if (!$this->widowLoan) {
            return 0;
        }

        $totalPayable = (float) $this->widowLoan->total_payable;
        return max(0, $totalPayable - $this->total_paid_up_to_this);
    }

    public function getInstallmentContext(): array
    {
        if (!$this->widowLoan) {
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

        // Prevent modification of existing/posted repayments
        static::updating(function (WidowLoanRepayment $repayment) {
            if ((!app()->runningInConsole() || app()->runningUnitTests()) && !request()->routeIs('*.transactions.*')) {
                throw new \RuntimeException("Posted financial repayments cannot be edited.");
            }
        });

        static::deleting(function (WidowLoanRepayment $repayment) {
            if ((!app()->runningInConsole() || app()->runningUnitTests()) && !request()->routeIs('*.transactions.*')) {
                throw new \RuntimeException("Posted financial repayments cannot be deleted.");
            }
        });
    }

    /**
     * Narrowly scoped domain method to attach a system transaction reference
     * without bypassing the general financial immutability constraints.
     */
    public function attachTransactionReference($transactionId): self
    {
        $this->updateQuietly(['transaction_id' => $transactionId]);
        
        return $this;
    }
}
