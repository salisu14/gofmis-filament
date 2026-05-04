<?php

namespace App\Models;

use App\Enums\WidowLoanStatus;
use App\Enums\LoanRepaymentFrequency;
use App\Traits\Approvable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ApprovalFlow;
use Illuminate\Support\Facades\DB;

class WidowLoan extends Model
{
    use HasUuids, SoftDeletes, Approvable;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'widow_loans';

    protected static function booted(): void
    {
        parent::booted();

        static::created(function ($loan) {
            if (is_null($loan->outstanding_balance)) {
                $loan->refreshBalance();
            }
        });
    }

    protected $fillable = [
        'widow_id',
        'bank_account_id',
        'principal_amount',
        'duration_months',
        'repayment_frequency',
        'total_payable',
        'total_paid',
        'outstanding_balance',
        'status',
        'disbursed_at',
        'approval_flow_id',
        'purpose',
        'fully_repaid',
        'loan_agreement_url',
        'reject_reason',
        'installment_number',
        'amount_due',
        'due_date',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'disbursed_at' => 'datetime',
        'fully_repaid' => 'boolean',
        'status' => WidowLoanStatus::class,
        'repayment_frequency' => LoanRepaymentFrequency::class,
    ];

    public function widow(): BelongsTo
    {
        return $this->belongsTo(Widow::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(WidowLoanRepayment::class, 'widow_loan_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(WidowLoanSchedule::class, 'widow_loan_id');
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactionable', 'transactionable_type', 'transactionable_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Approval Workflow Integration
    |--------------------------------------------------------------------------
    | These methods are called by the ApprovalService when the workflow status changes.
    */

    public function onApproved(ApprovalFlow $flow): void
    {
        // When the final approval is given, set the status to APPROVED
        $this->update(['status' => WidowLoanStatus::APPROVED]);
    }

    public function onRejected(ApprovalFlow $flow): void
    {
        // When rejected, update status and store the reason
        $this->update([
            'status' => WidowLoanStatus::REJECTED,
            'reject_reason' => $flow->rejection_reason,
        ]);
    }

    /**
     * Determine if the loan can be submitted for approval.
     */
    public function canSubmitForApproval(): bool
    {
        return $this->status === WidowLoanStatus::DRAFT && !$this->isAwaitingApproval();
    }

    // Helper methods
    public function isCompleted(): bool
    {
        return $this->status === WidowLoanStatus::COMPLETED;
    }

    public function isFullyRepaid(): bool
    {
        return $this->fully_repaid;
    }

    public function approve(?string $comments = null): void
    {
        $flow = $this->approvalFlow;
        if (!$flow) {
            throw new \Exception('No approval workflow found for this loan.');
        }

        app(\App\Services\ApprovalService::class)->approve($flow, auth()->user(), $comments);
    }

    public function reject(string $reason, ?string $comments = null): void
    {
        $flow = $this->approvalFlow;
        if (!$flow) {
            throw new \Exception('No approval workflow found for this loan.');
        }

        app(\App\Services\ApprovalService::class)->reject($flow, auth()->user(), $reason, $comments);
    }

    /**
     * Recalculate total paid and outstanding balance.
     */
    public function refreshBalance(): void
    {
        $totalPaid = $this->repayments()->sum('amount');
        $outstanding = max(0, $this->total_payable - $totalPaid);
        $fullyRepaid = $outstanding <= 0;

        $updateData = [
            'total_paid' => $totalPaid,
            'outstanding_balance' => $outstanding,
            'fully_repaid' => $fullyRepaid,
        ];

        if ($fullyRepaid && $this->status === WidowLoanStatus::DISBURSED) {
            $updateData['status'] = WidowLoanStatus::COMPLETED;
        }

        $this->update($updateData);

        // Sync the schedule marks (is_paid) based on the new total_paid
        $this->syncScheduleStatus();
    }

    /**
     * Generate the repayment ledger (weekly frequency).
     */
    public function generateLedger(): void
    {
        DB::transaction(function () {
            // Clear existing schedule if any
            $this->schedules()->delete();

            // Logic: Total Payable / Total Intervals = Installment
            $isWeekly = $this->repayment_frequency === LoanRepaymentFrequency::WEEKLY;
            $intervalsPerMonth = $isWeekly ? 4 : 1;
            $totalIntervals = $this->duration_months * $intervalsPerMonth;

            $installmentAmount = $this->total_payable / $totalIntervals;

            $startDate = $this->disbursed_at ?: now();

            for ($i = 1; $i <= $totalIntervals; $i++) {
                $dueDate = $isWeekly
                    ? $startDate->copy()->addWeeks($i)
                    : $startDate->copy()->addMonths($i);

                $this->schedules()->create([
                    'installment_number' => $i,
                    'amount_due' => $installmentAmount,
                    'due_date' => $dueDate,
                    'is_paid' => false,
                ]);
            }
        });
    }

    /**
     * Synchronize is_paid status in schedules based on total_paid.
     */
    public function syncScheduleStatus(): void
    {
        $totalPaid = (float) $this->total_paid;
        $runningSum = 0;

        // Reset all to unpaid first (or we can do it in the loop)
        $this->schedules()->update(['is_paid' => false]);

        $schedules = $this->schedules()->orderBy('installment_number')->get();

        foreach ($schedules as $schedule) {
            $runningSum += (float) $schedule->amount_due;

            // Allow a small margin for float precision if needed, but here simple <= should work
            if ($runningSum <= $totalPaid + 0.01) {
                $schedule->update(['is_paid' => true]);
            } else {
                break; // Stop once we exceed total paid
            }
        }
    }
}
