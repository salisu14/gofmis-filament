<?php

namespace App\Services;

use App\Enums\WidowLoanScheduleStatus;
use App\Enums\WidowLoanStatus;
use App\Models\User;
use App\Models\WidowLoan;
use App\Models\WidowLoanWriteOff;
use Illuminate\Support\Facades\DB;

class WidowLoanWriteOffService
{
    /**
     * Formally waives/writes off the remaining outstanding balance of a widow loan.
     */
    public function writeOff(
        WidowLoan $loan,
        User $actor,
        string $reason,
        ?string $verificationNotes = null,
        bool $allowReapplication = false,
        ?string $documentPath = null,
    ): WidowLoan {
        // Enforce role-based isolation: only super_admin
        if (! $actor->hasRole('super_admin')) {
            throw new \Exception('Unauthorized: Only super administrators can write off loans.');
        }

        return DB::transaction(function () use ($loan, $actor, $reason, $verificationNotes, $allowReapplication, $documentPath) {
            // Row-level lock to prevent double write-offs/race conditions
            $loan = WidowLoan::where('id', $loan->id)->lockForUpdate()->firstOrFail();

            // Re-verify constraints under lock
            if ($loan->status === WidowLoanStatus::WRITTEN_OFF) {
                throw new \Exception('This loan has already been written off.');
            }
            if ($loan->status === WidowLoanStatus::COMPLETED || $loan->fully_repaid) {
                throw new \Exception('Cannot write off a fully repaid/completed loan.');
            }
            if ($loan->status === WidowLoanStatus::REJECTED) {
                throw new \Exception('Cannot write off a rejected loan.');
            }
            if ($loan->status !== WidowLoanStatus::DISBURSED) {
                throw new \Exception('Only disbursed loans can be written off.');
            }

            $originalOutstandingBalance = (float) $loan->outstanding_balance;
            if ($originalOutstandingBalance <= 0) {
                throw new \Exception('This loan has no outstanding balance to write off.');
            }

            // Create immutable audit record
            WidowLoanWriteOff::create([
                'widow_loan_id' => $loan->id,
                'original_outstanding_balance' => $originalOutstandingBalance,
                'amount_written_off' => $originalOutstandingBalance,
                'write_off_reason' => $reason,
                'write_off_verification_notes' => $verificationNotes,
                'write_off_document_path' => $documentPath,
                'authorized_by' => $actor->id,
                'authorized_at' => now(),
            ]);

            // Update main loan properties
            $loan->update([
                'status' => WidowLoanStatus::WRITTEN_OFF,
                'amount_written_off' => $originalOutstandingBalance,
                'outstanding_balance' => 0.00,
                'fully_repaid' => false, // Ensure fully_repaid does NOT become true
                'written_off_at' => now(),
                'written_off_by' => $actor->id,
                'reapplication_allowed' => $allowReapplication,
                'reapplication_authorized_by' => $allowReapplication ? $actor->id : null,
                'reapplication_authorized_at' => $allowReapplication ? now() : null,
            ]);

            // Mark all unpaid schedule lines as WAIVED
            $loan->schedules()
                ->whereNull('superseded_at')
                ->where('is_paid', false)
                ->update([
                    'status' => WidowLoanScheduleStatus::WAIVED,
                ]);

            // Update any pending or endorsed write-off recommendations to EXECUTED
            $loan->writeOffRecommendations()
                ->whereIn('status', [
                    \App\Enums\WidowLoanWriteOffRecommendationStatus::PENDING,
                    \App\Enums\WidowLoanWriteOffRecommendationStatus::ENDORSED,
                ])
                ->update([
                    'status' => \App\Enums\WidowLoanWriteOffRecommendationStatus::EXECUTED,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                    'review_notes' => 'Executed via write-off service.',
                ]);

            // Log activity to audit log trail if activity logger helper is available
            if (function_exists('activity')) {
                activity()
                    ->performedOn($loan)
                    ->causedBy($actor)
                    ->withProperties([
                        'amount_written_off' => $originalOutstandingBalance,
                        'reason' => $reason,
                        'reapplication_allowed' => $allowReapplication,
                    ])
                    ->log('widow_loan_written_off');
            }

            return $loan;
        });
    }
}
