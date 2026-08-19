<?php

namespace App\Services;

use App\Enums\WidowLoanHardshipStatus;
use App\Models\User;
use App\Models\WidowLoan;
use App\Models\WidowLoanHardshipCase;
use App\Models\WidowLoanReliefPeriod;
use Illuminate\Support\Facades\DB;

class WidowLoanHardshipService
{
    /**
     * Report a new hardship case for a widow loan.
     */
    public function reportHardshipCase(
        string $loanId,
        string $reportedById,
        string $reasonCategory,
        string $reasonDetails,
        ?string $supportingDocumentPath = null
    ): WidowLoanHardshipCase {
        return DB::transaction(function () use ($loanId, $reportedById, $reasonCategory, $reasonDetails, $supportingDocumentPath) {
            $loan = WidowLoan::findOrFail($loanId);
            $reporter = User::findOrFail($reportedById);

            // Coordinator ownership / scoping security
            if ($reporter->hasRole('coordinator')) {
                $coordinatedZoneId = $reporter->coordinatedZone?->id;
                $widowZoneId = $loan->widow?->deceased?->zone_id;

                if (! $coordinatedZoneId || $coordinatedZoneId !== $widowZoneId) {
                    throw new \RuntimeException('Unauthorized: Coordinators can only report hardship cases for widows in their own zone.');
                }
            }

            return WidowLoanHardshipCase::create([
                'widow_loan_id' => $loan->id,
                'widow_id' => $loan->widow_id,
                'reported_by' => $reporter->id,
                'reason_category' => $reasonCategory,
                'reason_details' => $reasonDetails,
                'supporting_document_path' => $supportingDocumentPath,
                'status' => WidowLoanHardshipStatus::PENDING,
            ]);
        });
    }

    /**
     * Verify a reported hardship case.
     */
    public function verifyHardshipCase(string $caseId, string $verifiedById, string $verificationNotes): WidowLoanHardshipCase
    {
        $case = WidowLoanHardshipCase::findOrFail($caseId);

        if ($case->status !== WidowLoanHardshipStatus::PENDING) {
            throw new \RuntimeException('Only pending hardship cases can be verified.');
        }

        $case->update([
            'status' => WidowLoanHardshipStatus::VERIFIED,
            'verified_by' => $verifiedById,
            'verified_at' => now(),
            'verification_notes' => $verificationNotes,
        ]);

        return $case;
    }

    /**
     * Approve verified hardship case.
     */
    public function approveHardshipCase(string $caseId, string $approvedById, string $recommendedAction): WidowLoanHardshipCase
    {
        return DB::transaction(function () use ($caseId, $approvedById, $recommendedAction) {
            $case = WidowLoanHardshipCase::lockForUpdate()->findOrFail($caseId);

            if ($case->status !== WidowLoanHardshipStatus::VERIFIED) {
                throw new \RuntimeException('Only verified hardship cases can be approved.');
            }

            $case->update([
                'status' => WidowLoanHardshipStatus::APPROVED,
                'approved_by' => $approvedById,
                'approved_at' => now(),
                'recommended_action' => $recommendedAction,
            ]);

            // Set the loan's hardship_active flag to true
            $case->loan->update(['hardship_active' => true]);

            // Recalculate delinquency/performance
            app(WidowLoanDelinquencyService::class)->evaluateLoan($case->loan);

            return $case;
        });
    }

    /**
     * Reject a hardship case.
     */
    public function rejectHardshipCase(string $caseId, string $rejectedById, string $rejectionReason): WidowLoanHardshipCase
    {
        return DB::transaction(function () use ($caseId, $rejectedById, $rejectionReason) {
            $case = WidowLoanHardshipCase::lockForUpdate()->findOrFail($caseId);

            if (! in_array($case->status, [WidowLoanHardshipStatus::PENDING, WidowLoanHardshipStatus::VERIFIED])) {
                throw new \RuntimeException('Only pending or verified hardship cases can be rejected.');
            }

            $case->update([
                'status' => WidowLoanHardshipStatus::REJECTED,
                'rejected_by' => $rejectedById,
                'rejected_at' => now(),
                'rejection_reason' => $rejectionReason,
            ]);

            // Ensure loan's hardship_active stays false if not approved
            $case->loan->update(['hardship_active' => false]);

            return $case;
        });
    }

    /**
     * Grant temporary relief period.
     */
    public function createReliefPeriod(
        string $loanId,
        ?string $hardshipCaseId,
        string $startsAt,
        string $endsAt,
        string $reason,
        string $approvedById
    ): WidowLoanReliefPeriod {
        return DB::transaction(function () use ($loanId, $hardshipCaseId, $startsAt, $endsAt, $reason, $approvedById) {
            $loan = WidowLoan::lockForUpdate()->findOrFail($loanId);
            $approver = User::findOrFail($approvedById);

            // Create relief period record
            $relief = WidowLoanReliefPeriod::create([
                'widow_loan_id' => $loan->id,
                'hardship_case_id' => $hardshipCaseId,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'reason' => $reason,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'status' => 'active',
            ]);

            // Evaluate loan (which will respect active relief)
            app(WidowLoanDelinquencyService::class)->evaluateLoan($loan);

            return $relief;
        });
    }
}
