<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Repayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class LoanRepaymentQueryService
{
    /**
     * Get paginated repayments with search filtering.
     */
    public function getPaginatedRepayments(string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = Repayment::with(['loan', 'widow', 'user'])
            ->whereHas('loan', function (Builder $q) {
                $q->whereNull('paid_at'); // Only active loans
            });

        if ($search) {
            $query->whereHas('widow', function (Builder $q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereRaw("concat(first_name, ' ', last_name) like ?", ["%{$search}%"]);
            });
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get total expected amount from all active loans.
     */
    public function getTotalActiveLoanPrincipal(): float
    {
        return Loan::whereNull('paid_at')
            ->whereNotNull('collected_at') // Only collected loans count as "active debt"
            ->sum('amount');
    }

    /**
     * Get total amount repaid against active loans.
     */
    public function getTotalRepaymentsCollected(): float
    {
        return Repayment::whereHas('loan', function (Builder $q) {
            $q->whereNull('paid_at');
        })->sum('amount');
    }

    /**
     * Calculate the outstanding portfolio balance.
     */
    public function getPortfolioBalance(): float
    {
        return $this->getTotalActiveLoanPrincipal() - $this->getTotalRepaymentsCollected();
    }
}
