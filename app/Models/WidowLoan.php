<?php

namespace App\Models;

use App\Enums\LoanRepaymentFrequency;
use App\Enums\WidowLoanStatus;
use App\Traits\Approvable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class WidowLoan extends Model
{
    use Approvable, HasUuids, SoftDeletes;

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

        // -------------------------------------------------------
        // Zone-based global scope — coordinators only see loans
        // for widows that belong to their own zone.
        // -------------------------------------------------------
        static::addGlobalScope('zone', function ($query) {
            $user = auth()->user();

            if (! $user || $user->hasAnyRole(['admin', 'super_admin'])) {
                return;
            }

            $zoneId = $user->coordinatedZone?->id;

            if (! $zoneId) {
                return $query->whereRaw('1 = 0');
            }

            $query->whereHas('widow.deceased', function ($q) use ($zoneId) {
                $q->where('zone_id', $zoneId);
            });
        });
    }

    protected $fillable = [
        'widow_id',
        'bank_account_id',
        'disbursement_bank_id',
        'repayment_bank_id',
        'principal_amount',
        'original_principal_amount',
        'amount_adjustment_note',
        'amount_adjusted_by',
        'amount_adjusted_at',
        'duration_months',
        'repayment_frequency',
        'total_payable',
        'total_paid',
        'outstanding_balance',
        'status',
        'disbursed_at',
        'collected_at',
        'collected_by',
        'collector_name',
        'approval_flow_id',
        'purpose',
        'fully_repaid',
        'loan_agreement_url',
        'reject_reason',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'original_principal_amount' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'disbursed_at' => 'datetime',
        'collected_at' => 'datetime',
        'amount_adjusted_at' => 'datetime',
        'fully_repaid' => 'boolean',
        'status' => WidowLoanStatus::class,
        'repayment_frequency' => LoanRepaymentFrequency::class,
    ];

    // ==================================================
    // Relationships
    // ==================================================

    public function widow(): BelongsTo
    {
        return $this->belongsTo(Widow::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * The widow's own bank account that receives the disbursed funds.
     * Distinct from bankAccount() which is the foundation's internal disbursing account.
     */
    public function disbursementBank(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'disbursement_bank_id');
    }

    /**
     * The foundation's bank account where repayments for this loan should be credited.
     */
    public function repaymentBank(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'repayment_bank_id');
    }

    public function amountAdjuster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'amount_adjusted_by');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
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

    // ==================================================
    // Approval Workflow Hooks
    // ==================================================

    /**
     * Called by ApprovalService when the final approval step is completed.
     * Status transitions: PENDING → APPROVED
     */
    public function onApproved(ApprovalFlow $flow): void
    {
        $this->update(['status' => WidowLoanStatus::APPROVED]);
    }

    /**
     * Called by ApprovalService when the flow is rejected at any step.
     * Status transitions: PENDING → REJECTED
     */
    public function onRejected(ApprovalFlow $flow): void
    {
        $this->update([
            'status' => WidowLoanStatus::REJECTED,
            'reject_reason' => $flow->rejection_reason,
        ]);

        // Release the reserved funds
        app(\App\Services\WidowLoanService::class)->handleRejection($this);
    }

    // ==================================================
    // Guard Helpers — State Machine Checks
    // ==================================================

    /**
     * The loan can be submitted for approval only when it is a fresh draft.
     */
    public function canSubmitForApproval(): bool
    {
        return $this->status === WidowLoanStatus::DRAFT && ! $this->isAwaitingApproval();
    }

    /**
     * The loan can be disbursed only after final approval.
     */
    public function canDisburse(): bool
    {
        return $this->status === WidowLoanStatus::APPROVED;
    }

    /**
     * The loan can be marked as collected only after disbursement and before
     * being marked collected already.
     */
    public function canCollect(): bool
    {
        return $this->status === WidowLoanStatus::DISBURSED
            && is_null($this->collected_at);
    }

    /**
     * Repayments can only be recorded after the widow has confirmed collection.
     */
    public function canRecordRepayment(): bool
    {
        return $this->status === WidowLoanStatus::DISBURSED
            && ! is_null($this->collected_at)
            && ! $this->fully_repaid
            && $this->outstanding_balance > 0; // Added extra safety check
    }

    /**
     * The loan is fully settled.
     */
    public function isCompleted(): bool
    {
        return $this->status === WidowLoanStatus::COMPLETED;
    }

    /**
     * The loan balance has been fully paid off.
     */
    public function isFullyRepaid(): bool
    {
        return $this->fully_repaid;
    }

    // ==================================================
    // Approval Proxy Methods
    // ==================================================

    public function approve(?string $comments = null): void
    {
        $flow = $this->approvalFlow;
        if (! $flow) {
            throw new \Exception('No approval workflow found for this loan.');
        }

        app(\App\Services\ApprovalService::class)->approve($flow, auth()->user(), $comments);
    }

    public function reject(string $reason, ?string $comments = null): void
    {
        $flow = $this->approvalFlow;
        if (! $flow) {
            throw new \Exception('No approval workflow found for this loan.');
        }

        app(\App\Services\ApprovalService::class)->reject($flow, auth()->user(), $reason, $comments);
    }

    // ==================================================
    // Financial Ledger
    // ==================================================

    /**
     * Recalculate total_paid and outstanding_balance from actual repayment records.
     * This is the single authoritative recalculation — do not manually increment/decrement.
     */
    public function refreshBalance(): void
    {
        // Fallback to principal_amount if total_payable was somehow lost
        $totalPayable = (float) ($this->total_payable ?? $this->principal_amount);
        $totalPaid = (float) $this->repayments()->sum('amount');
        $outstanding = max(0, $totalPayable - $totalPaid);
        $fullyRepaid = $outstanding <= 0;

        $updateData = [
            'total_payable' => $totalPayable, // <-- Re-save it to fix the null data!
            'total_paid' => $totalPaid,
            'outstanding_balance' => $outstanding,
            'fully_repaid' => $fullyRepaid,
        ];

        if ($fullyRepaid && $this->status === WidowLoanStatus::DISBURSED) {
            $updateData['status'] = WidowLoanStatus::COMPLETED;
        }

        $this->update($updateData);

        // Sync schedule installment paid flags based on the new total_paid
        $this->syncScheduleStatus();
    }

    /**
     * Generate the repayment installment schedule.
     * MUST only be called after disbursed_at is set.
     * @throws \Throwable
     */
    public function generateLedger(): void
    {
        DB::transaction(function () {
            $this->schedules()->delete();

            $isWeekly = $this->repayment_frequency === LoanRepaymentFrequency::WEEKLY;
            $intervalsPerMonth = $isWeekly ? 4 : 1;
            $totalIntervals = $this->duration_months * $intervalsPerMonth;

            $startDate = $this->disbursed_at ?? now();

            if ($totalIntervals <= 0) {
                throw new \RuntimeException('Loan duration must generate at least one repayment installment.');
            }

            // Fallback to principal_amount if total_payable is missing
            $totalPayable = (float) ($this->total_payable ?? $this->principal_amount);

            $installmentAmount = round($totalPayable / $totalIntervals, 2);
            $scheduledTotal = 0;

            for ($i = 1; $i <= $totalIntervals; $i++) {
                $dueDate = $isWeekly
                    ? $startDate->copy()->addWeeks($i)
                    : $startDate->copy()->addMonths($i);

                $amountDue = $i === $totalIntervals
                    ? round($totalPayable - $scheduledTotal, 2)
                    : $installmentAmount;

                $this->schedules()->create([
                    'installment_number' => $i,
                    'amount_due' => $amountDue,
                    'due_date' => $dueDate,
                    'is_paid' => false,
                ]);

                $scheduledTotal += $amountDue;
            }
        });
    }
    /**
     * Synchronize the is_paid flag on schedule installments
     * based on the running cumulative total_paid.
     */
    public function syncScheduleStatus(): void
    {
        // Reset all to unpaid
        $this->schedules()->update([
            'is_paid' => false,
            'paid_at' => null,
        ]);

        $schedules = $this->schedules()->orderBy('installment_number')->get();
        $repayments = $this->repayments()
            ->orderBy('paid_at')
            ->orderBy('created_at')
            ->get(['amount', 'paid_at']);

        $requiredTotal = 0;
        $paidTotal = 0;
        $repaymentIndex = 0;
        $paidAt = null;

        // Walk both ledgers so each installment gets the date it became covered.
        foreach ($schedules as $schedule) {
            $requiredTotal += (float) $schedule->amount_due;

            while ($paidTotal + 0.01 < $requiredTotal && $repaymentIndex < $repayments->count()) {
                $repayment = $repayments[$repaymentIndex];
                $paidTotal += (float) $repayment->amount;
                $paidAt = $repayment->paid_at;
                $repaymentIndex++;
            }

            if ($paidTotal + 0.01 >= $requiredTotal) {
                $schedule->update([
                    'is_paid' => true,
                    'paid_at' => $paidAt,
                ]);
            } else {
                break;
            }
        }
    }
}
