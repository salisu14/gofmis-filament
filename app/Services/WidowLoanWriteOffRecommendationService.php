<?php

namespace App\Services;

use App\Enums\WidowLoanStatus;
use App\Enums\WidowLoanWriteOffRecommendationStatus;
use App\Models\WidowLoan;
use App\Models\WidowLoanWriteOffRecommendation;
use Illuminate\Support\Facades\DB;

class WidowLoanWriteOffRecommendationService
{
    /**
     * Recommend a loan for write-off.
     */
    public function recommendWriteOff(
        string $loanId,
        ?string $hardshipCaseId,
        ?string $recoveryCaseId,
        float $amount,
        string $reason,
        string $recommendedBy
    ): WidowLoanWriteOffRecommendation {
        return DB::transaction(function () use ($loanId, $hardshipCaseId, $recoveryCaseId, $amount, $reason, $recommendedBy) {
            $loan = WidowLoan::findOrFail($loanId);

            if ($loan->status !== WidowLoanStatus::DISBURSED) {
                throw new \RuntimeException('Only disbursed loans can be recommended for write-off.');
            }

            // Check if there is already an active recommendation
            $hasActive = $loan->writeOffRecommendations()
                ->whereIn('status', [
                    WidowLoanWriteOffRecommendationStatus::PENDING,
                    WidowLoanWriteOffRecommendationStatus::ENDORSED,
                ])
                ->exists();

            if ($hasActive) {
                throw new \RuntimeException('There is already a pending or endorsed write-off recommendation for this loan.');
            }

            return WidowLoanWriteOffRecommendation::create([
                'widow_loan_id' => $loan->id,
                'hardship_case_id' => $hardshipCaseId,
                'recovery_case_id' => $recoveryCaseId,
                'recommended_amount' => $amount,
                'reason' => $reason,
                'recommended_by' => $recommendedBy,
                'recommended_at' => now(),
                'status' => WidowLoanWriteOffRecommendationStatus::PENDING,
            ]);
        });
    }

    /**
     * Review/Endorse/Reject a write-off recommendation.
     */
    public function reviewRecommendation(
        string $recommendationId,
        string $reviewedBy,
        string $status,
        ?string $reviewNotes = null
    ): WidowLoanWriteOffRecommendation {
        return DB::transaction(function () use ($recommendationId, $reviewedBy, $status, $reviewNotes) {
            $recommendation = WidowLoanWriteOffRecommendation::lockForUpdate()->findOrFail($recommendationId);

            if ($recommendation->status === WidowLoanWriteOffRecommendationStatus::EXECUTED) {
                throw new \RuntimeException('Cannot review a recommendation that has already been executed.');
            }

            $enumStatus = WidowLoanWriteOffRecommendationStatus::from($status);

            $recommendation->update([
                'status' => $enumStatus,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'review_notes' => $reviewNotes,
            ]);

            return $recommendation;
        });
    }
}
