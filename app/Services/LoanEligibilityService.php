<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Widow;
use Illuminate\Database\Eloquent\Collection;

class LoanEligibilityService
{
    /**
     * Check if a widow has no active unpaid loans.
     */
    public function canApplyForLoan(Widow $widow): bool
    {
        // A widow can apply if they have no active (pending or approved) unpaid loans.
        // We check if paid_at is null AND status is not rejected.
        $hasActiveLoan = $widow->loans()
            ->whereNull('paid_at')
            ->where('status', '!=', 'rejected')
            ->exists();

        return !$hasActiveLoan;
    }

    /**
     * Get approved loans for the authenticated user's widows.
     */
    public function getApprovedLoansForAuthUser(): Collection
    {
        // Assuming User hasMany Widows
        return auth()->user()->widows
            ->flatMap->loans
            ->sortByDesc('created_at')
            ->whereNotNull('approved_at')
            ->whereNull('paid_at');
    }

    /**
     * Get rejected loans for the authenticated user's widows.
     */
    public function getRejectedLoansForAuthUser(): Collection
    {
        return auth()->user()->widows
            ->flatMap->loans
            ->sortByDesc('created_at')
            ->whereNotNull('reject_reason');
    }

    /**
     * Get pending loans.
     */
    public function getPendingLoansForAuthUser(): Collection
    {
        return auth()->user()->widows
            ->flatMap->loans
            ->sortByDesc('created_at')
            ->whereNull('approved_at')
            ->whereNull('reject_reason');
    }
}
