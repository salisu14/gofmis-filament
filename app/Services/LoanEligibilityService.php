<?php

namespace App\Services;

use App\Enums\WidowLoanStatus;
use App\Models\Widow;
use App\Models\WidowLoan;
use Illuminate\Database\Eloquent\Collection;

class LoanEligibilityService
{
    /**
     * Check if a widow is eligible to apply for a new WidowLoan.
     *
     * A widow is eligible when:
     *  - She is not remarried.
     *  - She has no active WidowLoan (draft/pending/approved/disbursed/collected/defaulted).
     *
     * NOTE: This uses widowLoans() — the dedicated WidowLoan model — not the
     * generic Loan model (loans() relationship).
     */
    public function canApplyForLoan(Widow $widow): bool
    {
        return $widow->canApplyForLoan();
    }

    /**
     * Get approved (APPROVED status) WidowLoans that have not yet been disbursed.
     */
    public function getApprovedPendingDisbursement(): \Illuminate\Database\Eloquent\Builder
    {
        return WidowLoan::where('status', WidowLoanStatus::APPROVED);
    }

    /**
     * Get all disbursed loans still awaiting collection confirmation.
     */
    public function getDisbursedAwaitingCollection(): \Illuminate\Database\Eloquent\Builder
    {
        return WidowLoan::where('status', WidowLoanStatus::DISBURSED)
            ->whereNull('collected_at');
    }

    /**
     * Get all active loans (disbursed with outstanding balance).
     *
     * NOTE: There is no COLLECTED status — the collected_at timestamp is the
     * collection signal while status remains DISBURSED throughout repayments.
     */
    public function getActiveLoans(): \Illuminate\Database\Eloquent\Builder
    {
        return WidowLoan::where('status', WidowLoanStatus::DISBURSED)
            ->where('fully_repaid', false);
    }
}
