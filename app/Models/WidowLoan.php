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

        // -------------------------------------------------------
        // Zone-based global scope — coordinators only see loans
        // for widows that belong to their own zone.
        // -------------------------------------------------------
        static::addGlobalScope('zone', function ($query) {
            $user = auth()->user();

            if (!$user || $user->hasAnyRole(['admin', 'super_admin'])) {
                return;
            }

            $zoneId = $user->coordinatedZone?->id;

            if (!$zoneId) {
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
        'principal_amount',
        'duration_months',
        'repayment_frequency',
        'total_payable',
        'total_paid',
        'outstanding_balance',
        'status',
        'disbursed_at',
        'collected_at',
        'approval_flow_id',
        'purpose',
        'fully_repaid',
        'loan_agreement_url',
        'reject_reason',
    ];

    protected $casts = [
        'principal_amount'    => 'decimal:2',
        'total_payable'       => 'decimal:2',
        'total_paid'          => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'disbursed_at'        => 'datetime',
        'collected_at'        => 'datetime',
        'fully_repaid'        => 'boolean',
        'status'              => WidowLoanStatus::class,
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
            'status'        => WidowLoanStatus::REJECTED,
            'reject_reason' => $flow->rejection_reason,
        ]);
    }

    // ==================================================
    // Guard Helpers — State Machine Checks
    // ==================================================

    /**
     * The loan can be submitted for approval only when it is a fresh draft.
     */
    public function canSubmitForApproval(): bool
    {
        return $this->status === WidowLoanStatus::DRAFT && !$this->isAwaitingApproval();
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
     * Repayments can only be recorded after the loan has been disbursed.
     * Collection confirmation (collected_at) is encouraged but not mandatory
     * to block repayments — the status signal is DISBURSED.
     */
    public function canRecordRepayment(): bool
    {
        return $this->status === WidowLoanStatus::DISBURSED;
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

    // ==================================================
    // Financial Ledger
    // ==================================================

    /**
     * Recalculate total_paid and outstanding_balance from actual repayment records.
     * This is the single authoritative recalculation — do not manually increment/decrement.
     */
    public function refreshBalance(): void
    {
        $totalPaid   = $this->repayments()->sum('amount');
        $outstanding = max(0, $this->total_payable - $totalPaid);
        $fullyRepaid = $outstanding <= 0;

        $updateData = [
            'total_paid'          => $totalPaid,
            'outstanding_balance' => $outstanding,
            'fully_repaid'        => $fullyRepaid,
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
     */
    public function generateLedger(): void
    {
        DB::transaction(function () {
            // Clear any stale schedule entries
            $this->schedules()->delete();

            $isWeekly       = $this->repayment_frequency === LoanRepaymentFrequency::WEEKLY;
            $intervalsPerMonth = $isWeekly ? 4 : 1;
            $totalIntervals = $this->duration_months * $intervalsPerMonth;

            // Anchor due dates to the actual disbursement date
            $startDate = $this->disbursed_at ?? now();

            $installmentAmount = round($this->total_payable / $totalIntervals, 2);

            for ($i = 1; $i <= $totalIntervals; $i++) {
                $dueDate = $isWeekly
                    ? $startDate->copy()->addWeeks($i)
                    : $startDate->copy()->addMonths($i);

                $this->schedules()->create([
                    'installment_number' => $i,
                    'amount_due'         => $installmentAmount,
                    'due_date'           => $dueDate,
                    'is_paid'            => false,
                ]);
            }
        });
    }

    /**
     * Synchronize the is_paid flag on schedule installments
     * based on the running cumulative total_paid.
     */
    public function syncScheduleStatus(): void
    {
        $totalPaid  = (float) $this->total_paid;
        $runningSum = 0;

        // Reset all to unpaid
        $this->schedules()->update(['is_paid' => false]);

        $schedules = $this->schedules()->orderBy('installment_number')->get();

        foreach ($schedules as $schedule) {
            $runningSum += (float) $schedule->amount_due;

            if ($runningSum <= $totalPaid + 0.01) {
                $schedule->update(['is_paid' => true]);
            } else {
                break;
            }
        }
    }
}
