<?php

namespace App\Services;

use App\Enums\WidowLoanPromiseStatus;
use App\Enums\WidowLoanRecoveryActivityType;
use App\Enums\WidowLoanRecoveryStatus;
use App\Models\WidowLoanPromise;
use App\Models\WidowLoanRecoveryActivity;
use App\Models\WidowLoanRecoveryCase;
use Illuminate\Support\Facades\DB;

class WidowLoanRecoveryService
{
    /**
     * Record a recovery activity on a case.
     */
    public function createRecoveryActivity(
        string $caseId,
        WidowLoanRecoveryActivityType $type,
        string $notes,
        string $contactMethod,
        ?float $promiseAmount = null,
        ?string $promiseDate = null,
        ?string $nextFollowUpAt = null,
        ?string $performedBy = null
    ): WidowLoanRecoveryActivity {
        return DB::transaction(function () use (
            $caseId,
            $type,
            $notes,
            $contactMethod,
            $promiseAmount,
            $promiseDate,
            $nextFollowUpAt,
            $performedBy
        ) {
            $case = WidowLoanRecoveryCase::lockForUpdate()->findOrFail($caseId);
            $loan = $case->loan;

            $activity = WidowLoanRecoveryActivity::create([
                'recovery_case_id' => $case->id,
                'widow_loan_id' => $loan->id,
                'activity_type' => $type,
                'notes' => $notes,
                'contact_method' => $contactMethod,
                'promise_amount' => $promiseAmount,
                'promise_date' => $promiseDate,
                'performed_by' => $performedBy ?: auth()->id(),
                'performed_at' => now(),
                'next_follow_up_at' => $nextFollowUpAt,
            ]);

            // Update case current action and next follow up
            $case->update([
                'current_action' => $type->getLabel(),
                'next_action_at' => $nextFollowUpAt,
            ]);

            // If a promise to pay is registered
            if ($type === WidowLoanRecoveryActivityType::PROMISE_TO_PAY && $promiseAmount > 0 && $promiseDate) {
                WidowLoanPromise::create([
                    'recovery_case_id' => $case->id,
                    'widow_loan_id' => $loan->id,
                    'promised_amount' => $promiseAmount,
                    'promised_date' => $promiseDate,
                    'status' => WidowLoanPromiseStatus::OPEN,
                ]);

                $case->update(['status' => WidowLoanRecoveryStatus::PROMISE_TO_PAY]);
            } else {
                // If it was promise to pay, keep it. Otherwise, set in progress.
                if ($case->status === WidowLoanRecoveryStatus::OPEN) {
                    $case->update(['status' => WidowLoanRecoveryStatus::IN_PROGRESS]);
                }
            }

            // Sync with loan attributes
            $loan->update([
                'recovery_status' => $case->status->value,
                'last_recovery_action_at' => now(),
                'next_recovery_action_at' => $nextFollowUpAt,
            ]);

            return $activity;
        });
    }

    /**
     * Mark a promise to pay as fulfilled.
     */
    public function fulfillPromise(string $promiseId): WidowLoanPromise
    {
        return DB::transaction(function () use ($promiseId) {
            $promise = WidowLoanPromise::lockForUpdate()->findOrFail($promiseId);

            if ($promise->status !== WidowLoanPromiseStatus::OPEN) {
                throw new \RuntimeException('Only open promises can be fulfilled.');
            }

            $promise->update([
                'status' => WidowLoanPromiseStatus::FULFILLED,
                'fulfilled_at' => now(),
            ]);

            $case = $promise->recoveryCase;

            // Check if there are other pending promises
            $hasPending = $case->promises()->where('status', WidowLoanPromiseStatus::OPEN)->exists();
            if (! $hasPending) {
                $case->update(['status' => WidowLoanRecoveryStatus::IN_PROGRESS]);
            }

            // Refresh loan delinquency
            app(WidowLoanDelinquencyService::class)->evaluateLoan($promise->loan);

            return $promise;
        });
    }

    /**
     * Mark a promise to pay as broken.
     */
    public function breakPromise(string $promiseId): WidowLoanPromise
    {
        return DB::transaction(function () use ($promiseId) {
            $promise = WidowLoanPromise::lockForUpdate()->findOrFail($promiseId);

            if ($promise->status !== WidowLoanPromiseStatus::OPEN) {
                throw new \RuntimeException('Only open promises can be broken.');
            }

            $promise->update([
                'status' => WidowLoanPromiseStatus::BROKEN,
                'broken_at' => now(),
            ]);

            $case = $promise->recoveryCase;
            $case->update(['status' => WidowLoanRecoveryStatus::ESCALATED]);

            // Sync with loan
            $promise->loan->update(['recovery_status' => WidowLoanRecoveryStatus::ESCALATED->value]);

            return $promise;
        });
    }
}
