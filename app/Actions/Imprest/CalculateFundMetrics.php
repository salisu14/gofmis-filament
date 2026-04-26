<?php

namespace App\Actions\Imprest;

use App\Models\ImprestFund;
use App\Repositories\Contracts\Imprest\ImprestTransactionRepositoryInterface;

class CalculateFundMetrics
{
    public function __construct(
        private readonly ImprestTransactionRepositoryInterface $transactionRepo,
    ) {}

    public function execute(int $fundId): array
    {
        $fund = ImprestFund::findOrFail($fundId);
        $startOfMonth = now()->startOfMonth()->toDateString();
        $today = now()->toDateString();

        $monthlySpent = $this->transactionRepo->getTotalSpentInPeriod($fundId, $startOfMonth, $today);
        $pendingCount = $fund->pendingTransactions()->count();
        $missingReceipts = $this->transactionRepo->getMissingReceipts($fundId)->count();

        return [
            'fund_id' => $fundId,
            'authorized_amount' => (float) $fund->authorized_amount,
            'current_balance' => (float) $fund->current_balance,
            'available_percentage' => $fund->authorized_amount > 0
                ? round(($fund->current_balance / $fund->authorized_amount) * 100, 2)
                : 0,
            'monthly_spent' => $monthlySpent,
            'pending_transactions' => $pendingCount,
            'missing_receipts' => $missingReceipts,
            'days_since_reconciliation' => $fund->last_reconciled_at
                ? $fund->last_reconciled_at->diffInDays(now())
                : null,
            'is_low_balance' => $fund->isLowBalance(),
        ];
    }
}
