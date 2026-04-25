<?php

namespace App\Services;

use App\Models\Widow;
use App\Models\Loan;

class LoanService
{
    /**
     * Check if a widow is eligible for a NEW loan.
     * Rule: Must have fully repaid previous loans.
     */
    public function canApplyForLoan(Widow $widow): bool
    {
        // Check for any existing loans that are NOT fully repaid
        $hasUnpaidLoan = $widow->loans()
            ->where('fully_repaid', false)
            ->exists();

        return !$hasUnpaidLoan;
    }

    /**
     * Get outstanding balance for a specific loan.
     */
    public function getOutstandingBalance(string $loanId): float
    {
        $loan = Loan::findOrFail($loanId);
        $totalPaid = $loan->repayments()->sum('amount');

        return max(0, $loan->amount - $totalPaid);
    }
}
