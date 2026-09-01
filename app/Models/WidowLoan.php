<?php

namespace App\Models;

use App\Enums\LoanRepaymentFrequency;
use App\Enums\WidowLoanPerformanceStatus;
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

        static::updating(function ($loan) {
            // Lock financially material fields if disbursed or has repayments.
            $hasFinancialActivity = $loan->getOriginal('status') === WidowLoanStatus::DISBURSED
                || $loan->repayments()->exists();

            if ($hasFinancialActivity) {
                $financialFields = [
                    'widow_id',
                    'principal_amount',
                    'total_payable',
                    'duration_months',
                    'repayment_frequency',
                    'bank_account_id',
                    'disbursement_bank_id',
                    'repayment_bank_id',
                ];

                foreach ($financialFields as $field) {
                    if ($loan->isDirty($field)) {
                        throw new \RuntimeException("Cannot modify financial term '{$field}' on a loan that has already been disbursed or has financial activity.");
                    }
                }
            }
        });

        static::deleting(function ($loan) {
            $hasFinancialActivity = in_array($loan->getOriginal('status'), [
                \App\Enums\WidowLoanStatus::DISBURSED,
                \App\Enums\WidowLoanStatus::COMPLETED,
                \App\Enums\WidowLoanStatus::WRITTEN_OFF,
            ]) || $loan->repayments()->exists() || $loan->schedules()->exists();

            if ($hasFinancialActivity) {
                throw new \RuntimeException('Cannot delete a loan that has already been financially active or scheduled.');
            }
        });

        // -------------------------------------------------------
        // Zone-based global scope — coordinators only see loans
        // for widows that belong to their own zone.
        // -------------------------------------------------------
        static::addGlobalScope('zone', function ($query) {
            $user = auth()->user();

            if (! $user || $user->hasAnyRole(['admin', 'super_admin']) || $user->isDemoObserver()) {
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
        'amount_written_off',
        'written_off_at',
        'written_off_by',
        'reapplication_allowed',
        'reapplication_authorized_by',
        'reapplication_authorized_at',
        'status',
        'performance_status',
        'first_overdue_at',
        'last_payment_at',
        'days_past_due',
        'overdue_amount',
        'arrears_installments',
        'defaulted_at',
        'default_reason',
        'recovery_status',
        'last_recovery_action_at',
        'next_recovery_action_at',
        'hardship_active',
        'disbursed_at',
        'collected_at',
        'collected_by',
        'collector_name',
        'approval_flow_id',
        'purpose',
        'fully_repaid',
        'loan_agreement_url',
        'reject_reason',
        'date_issued',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'original_principal_amount' => 'decimal:2',
        'total_payable' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'amount_written_off' => 'decimal:2',
        'disbursed_at' => 'datetime',
        'collected_at' => 'datetime',
        'amount_adjusted_at' => 'datetime',
        'written_off_at' => 'datetime',
        'reapplication_allowed' => 'boolean',
        'reapplication_authorized_at' => 'datetime',
        'fully_repaid' => 'boolean',
        'status' => WidowLoanStatus::class,
        'performance_status' => WidowLoanPerformanceStatus::class,
        'repayment_frequency' => LoanRepaymentFrequency::class,
        'first_overdue_at' => 'datetime',
        'last_payment_at' => 'datetime',
        'days_past_due' => 'integer',
        'overdue_amount' => 'decimal:2',
        'arrears_installments' => 'integer',
        'defaulted_at' => 'datetime',
        'last_recovery_action_at' => 'datetime',
        'next_recovery_action_at' => 'datetime',
        'hardship_active' => 'boolean',
    ];

    // ==================================================
    // Relationships
    // ==================================================

    public function widow(): BelongsTo
    {
        return $this->belongsTo(Widow::class);
    }

    public function counterFundings(): HasMany
    {
        return $this->hasMany(WidowLoanCounterFunding::class);
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

    public function writeOff(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WidowLoanWriteOff::class, 'widow_loan_id');
    }

    public function writtenOffBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'written_off_by');
    }

    public function reapplicationAuthorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reapplication_authorized_by');
    }

    public function hardshipCases(): HasMany
    {
        return $this->hasMany(WidowLoanHardshipCase::class, 'widow_loan_id');
    }

    public function reliefPeriods(): HasMany
    {
        return $this->hasMany(WidowLoanReliefPeriod::class, 'widow_loan_id');
    }

    public function restructures(): HasMany
    {
        return $this->hasMany(WidowLoanRestructure::class, 'widow_loan_id');
    }

    public function recoveryCases(): HasMany
    {
        return $this->hasMany(WidowLoanRecoveryCase::class, 'widow_loan_id');
    }

    public function writeOffRecommendations(): HasMany
    {
        return $this->hasMany(WidowLoanWriteOffRecommendation::class, 'widow_loan_id');
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
        return ($this->status === WidowLoanStatus::DRAFT || ($this->status === WidowLoanStatus::PENDING && ! $this->approvalFlow))
            && ! $this->isAwaitingApproval();
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
        $totalPayable = (float) ($this->total_payable ?? $this->principal_amount);
        $totalPaid = (float) $this->repayments()->sum('amount');
        $amountWrittenOff = (float) ($this->amount_written_off ?? 0);
        // Counter funding is derived from the authoritative ledger so the
        // denormalized counter_funded_amount column can never drift from the
        // recorded history (single source of truth).
        $counterFunded = (float) $this->counterFundings()->sum('amount');
        $outstanding = max(0, $totalPayable - $totalPaid - $amountWrittenOff - $counterFunded);

        $fullyRepaid = $outstanding <= 0 && $this->status !== WidowLoanStatus::WRITTEN_OFF && $amountWrittenOff <= 0;

        $updateData = [
            'total_payable' => $totalPayable, // <-- Re-save it to fix the null data!
            'total_paid' => $totalPaid,
            'outstanding_balance' => $outstanding,
            'fully_repaid' => $fullyRepaid,
        ];

        if ($fullyRepaid && $this->status === WidowLoanStatus::DISBURSED) {
            $updateData['status'] = WidowLoanStatus::COMPLETED;
            $updateData['performance_status'] = WidowLoanPerformanceStatus::CURRENT;
        }

        if ($this->status === WidowLoanStatus::WRITTEN_OFF) {
            $updateData['performance_status'] = WidowLoanPerformanceStatus::WRITTEN_OFF;
        }

        $this->update($updateData);

        // Sync schedule installment paid flags based on the new total_paid
        $this->syncScheduleStatus();

        // Recalculate delinquency/performance if active (not written off or completed)
        if ($this->status === WidowLoanStatus::DISBURSED) {
            app(\App\Services\WidowLoanDelinquencyService::class)->evaluateLoan($this);
        }
    }

    public function getTotalCounterFundedAttribute(): float
    {
        return (float) $this->counterFundings()->sum('amount');
    }

    /**
     * Generate the repayment installment schedule.
     * MUST only be called after disbursed_at is set.
     *
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
                    'status' => \App\Enums\WidowLoanScheduleStatus::PENDING->value,
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
        // For a written-off loan, we want to leave already paid installments as PAID
        // and outstanding unpaid installments as WAIVED. We should not reset everything blindly.
        if ($this->status === WidowLoanStatus::WRITTEN_OFF) {
            $this->schedules()
                ->where('is_paid', false)
                ->where('status', '!=', \App\Enums\WidowLoanScheduleStatus::WAIVED->value)
                ->update([
                    'status' => \App\Enums\WidowLoanScheduleStatus::WAIVED->value,
                ]);

            return;
        }

        // Reset all to unpaid (except waived ones, just in case)
        $this->schedules()
            ->whereNull('superseded_at')
            ->where('status', '!=', \App\Enums\WidowLoanScheduleStatus::WAIVED->value)
            ->update([
                'is_paid' => false,
                'paid_at' => null,
                'status' => \App\Enums\WidowLoanScheduleStatus::PENDING->value,
            ]);

        $schedules = $this->schedules()
            ->whereNull('superseded_at')
            ->where('status', '!=', \App\Enums\WidowLoanScheduleStatus::WAIVED->value)
            ->orderBy('installment_number')
            ->get();

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
                    'status' => \App\Enums\WidowLoanScheduleStatus::PAID->value,
                ]);
            } else {
                // Check if overdue
                $isOverdue = $schedule->due_date->isPast();
                $schedule->update([
                    'is_paid' => false,
                    'paid_at' => null,
                    'status' => $isOverdue
                        ? \App\Enums\WidowLoanScheduleStatus::OVERDUE->value
                        : \App\Enums\WidowLoanScheduleStatus::PENDING->value,
                ]);
            }
        }
    }
}
