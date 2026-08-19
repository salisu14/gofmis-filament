<?php

namespace App\Services;

use App\Enums\LoanRepaymentFrequency;
use App\Enums\WidowLoanRestructureStatus;
use App\Enums\WidowLoanStatus;
use App\Models\WidowLoan;
use App\Models\WidowLoanRestructure;
use Illuminate\Support\Facades\DB;

class WidowLoanRestructureService
{
    /**
     * Propose a restructure for a loan.
     */
    public function proposeRestructure(
        string $loanId,
        ?string $hardshipCaseId,
        int $newDurationMonths,
        LoanRepaymentFrequency $newFrequency,
        float $newInstallmentAmount,
        string $effectiveDate,
        string $reason,
        string $requestedBy
    ): WidowLoanRestructure {
        return DB::transaction(function () use (
            $loanId,
            $hardshipCaseId,
            $newDurationMonths,
            $newFrequency,
            $newInstallmentAmount,
            $effectiveDate,
            $reason,
            $requestedBy
        ) {
            $loan = WidowLoan::lockForUpdate()->findOrFail($loanId);

            if ($loan->status !== WidowLoanStatus::DISBURSED) {
                throw new \RuntimeException('Only disbursed loans can be restructured.');
            }

            // Check if there is already a pending restructure proposal
            $hasPending = $loan->restructures()
                ->where('status', WidowLoanRestructureStatus::PENDING_APPROVAL)
                ->exists();

            if ($hasPending) {
                throw new \RuntimeException('There is already a pending restructuring proposal for this loan.');
            }

            $oldRemainingDuration = $loan->schedules()
                ->where('status', '!=', \App\Enums\WidowLoanScheduleStatus::WAIVED->value)
                ->where('is_paid', false)
                ->count();

            return WidowLoanRestructure::create([
                'widow_loan_id' => $loan->id,
                'hardship_case_id' => $hardshipCaseId,
                'old_outstanding_balance' => $loan->outstanding_balance,
                'old_duration_remaining' => $oldRemainingDuration,
                'new_duration' => $newDurationMonths,
                'new_repayment_frequency' => $newFrequency,
                'new_installment_amount' => $newInstallmentAmount,
                'effective_date' => $effectiveDate,
                'reason' => $reason,
                'status' => WidowLoanRestructureStatus::PENDING_APPROVAL,
                'requested_by' => $requestedBy,
            ]);
        });
    }

    /**
     * Approve and apply a restructure.
     */
    public function approveAndApply(string $restructureId, string $approvedBy): void
    {
        DB::transaction(function () use ($restructureId, $approvedBy) {
            $restructure = WidowLoanRestructure::lockForUpdate()->findOrFail($restructureId);

            if ($restructure->status !== WidowLoanRestructureStatus::PENDING_APPROVAL) {
                throw new \RuntimeException('This restructure proposal is not pending approval.');
            }

            $loan = WidowLoan::lockForUpdate()->findOrFail($restructure->widow_loan_id);

            // 1. Mark the restructure as approved and applied
            $restructure->update([
                'status' => WidowLoanRestructureStatus::APPLIED,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);

            // 2. Identify the highest current schedule version
            $currentMaxVersion = (int) $loan->schedules()->max('schedule_version') ?: 1;
            $newVersion = $currentMaxVersion + 1;

            // 3. Mark all outstanding, unpaid schedules of the current version as superseded
            $loan->schedules()
                ->where('is_paid', false)
                ->where('schedule_version', $currentMaxVersion)
                ->whereNull('superseded_at')
                ->update([
                    'superseded_at' => now(),
                    'superseded_by' => $restructure->id,
                ]);

            // 4. Generate the new schedule installments
            $isWeekly = $restructure->new_repayment_frequency === LoanRepaymentFrequency::WEEKLY;
            $intervalsPerMonth = $isWeekly ? 4 : 1;
            $totalIntervals = $restructure->new_duration * $intervalsPerMonth;

            $startDate = \Carbon\Carbon::parse($restructure->effective_date);
            $totalPayable = (float) $restructure->old_outstanding_balance;

            $installmentAmount = round($totalPayable / $totalIntervals, 2);
            $scheduledTotal = 0;

            for ($i = 1; $i <= $totalIntervals; $i++) {
                $dueDate = $isWeekly
                    ? $startDate->copy()->addWeeks($i)
                    : $startDate->copy()->addMonths($i);

                $amountDue = $i === $totalIntervals
                    ? round($totalPayable - $scheduledTotal, 2)
                    : $installmentAmount;

                $loan->schedules()->create([
                    'installment_number' => $i,
                    'amount_due' => $amountDue,
                    'due_date' => $dueDate,
                    'is_paid' => false,
                    'status' => \App\Enums\WidowLoanScheduleStatus::PENDING->value,
                    'schedule_version' => $newVersion,
                ]);

                $scheduledTotal += $amountDue;
            }

            // 5. Evaluate the loan performance
            app(WidowLoanDelinquencyService::class)->evaluateLoan($loan);
        });
    }

    /**
     * Reject a restructure proposal.
     */
    public function reject(string $restructureId, string $rejectedBy): void
    {
        $restructure = WidowLoanRestructure::findOrFail($restructureId);

        if ($restructure->status !== WidowLoanRestructureStatus::PENDING_APPROVAL) {
            throw new \RuntimeException('This restructure proposal is not pending approval.');
        }

        $restructure->update([
            'status' => WidowLoanRestructureStatus::REJECTED,
            'approved_by' => $rejectedBy, // reuse approved_by column/logic or keep as auditor field
            'approved_at' => now(),
        ]);
    }
}
