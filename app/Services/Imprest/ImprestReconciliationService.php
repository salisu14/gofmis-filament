<?php

namespace App\Services\Imprest;

use App\Data\Imprest\ReconcileFundDto;
use App\Events\Imprest\ReconciliationCompleted;
use App\Events\Imprest\ReconciliationFlagged;
use App\Models\ImprestReconciliation;
use App\Models\ImprestReplenishment;
use App\Repositories\Contracts\Imprest\ImprestFundRepositoryInterface;
use App\Repositories\Contracts\Imprest\ImprestReconciliationRepositoryInterface;
use App\Repositories\Contracts\Imprest\ImprestTransactionRepositoryInterface;
use App\Services\Contracts\Imprest\ImprestReconciliationServiceInterface;
use Illuminate\Support\Facades\DB;

class ImprestReconciliationService implements ImprestReconciliationServiceInterface
{
    public function __construct(
        private ImprestReconciliationRepositoryInterface $reconciliationRepo,
        private ImprestTransactionRepositoryInterface    $transactionRepo,
        private ImprestFundRepositoryInterface           $fundRepo,
    ) {}

    public function reconcile(ReconcileFundDto $dto): ImprestReconciliation
    {
        return DB::transaction(function () use ($dto) {
            $fund = $this->fundRepo->findById($dto->fundId);

            if (!$fund) {
                throw new \RuntimeException('Fund not found');
            }

            if ($fund->status !== 'active') {
                throw new \RuntimeException('Only active funds can be reconciled.');
            }

            $receiptsTotal = $this->transactionRepo->getTotalSpentInPeriod(
                $dto->fundId,
                $dto->reconciliationDate->copy()->startOfMonth()->toDateString(),
                $dto->reconciliationDate->toDateString()
            );

            $dto = new ReconcileFundDto(
                fundId: $dto->fundId,
                reconciliationDate: $dto->reconciliationDate,
                cashOnHand: $dto->cashOnHand,
                receiptsTotal: $receiptsTotal,
                auditorId: $dto->auditorId,
                custodianId: $dto->custodianId,
                notes: $dto->notes,
                varianceExplanation: $dto->varianceExplanation,
            );

            $reconciliation = $this->reconciliationRepo->create($dto);

            $this->fundRepo->updateLastReconciled($dto->fundId);

            if ($reconciliation->status === 'flagged') {
                event(new ReconciliationFlagged($reconciliation));
            } else {
                event(new ReconciliationCompleted($reconciliation));
            }

            return $reconciliation;
        });
    }

    public function acknowledge(string $reconciliationId, string $custodianId): ImprestReconciliation
    {
        $reconciliation = $this->reconciliationRepo->findById($reconciliationId);

        if (!$reconciliation) {
            throw new \RuntimeException('Reconciliation not found');
        }

        if ($reconciliation->custodian_id !== $custodianId) {
            throw new \RuntimeException('Only the assigned custodian can acknowledge');
        }

        $reconciliation->update(['custodian_acknowledged' => true]);

        return $reconciliation->fresh();
    }

    public function complete(string $reconciliationId): ImprestReconciliation
    {
        return $this->reconciliationRepo->complete($reconciliationId);
    }

    public function flag(string $reconciliationId, string $reason): ImprestReconciliation
    {
        return $this->reconciliationRepo->flag($reconciliationId, $reason);
    }

    public function getReconciliationReport(string $fundId, string $start, string $end): array
    {
        $transactions = $this->transactionRepo->getInDateRange($fundId, $start, $end);
        $fund = $this->fundRepo->findById($fundId);
        $processedReplenishments = (float) ImprestReplenishment::query()
            ->where('fund_id', $fundId)
            ->where('status', 'processed')
            ->whereDate('period_start', '<=', $end)
            ->whereDate('period_end', '>=', $start)
            ->whereDate('processed_at', '<=', $end)
            ->sum('amount');
        $totalSpent = (float) $transactions->sum('total_price');

        return [
            'fund' => $fund->toArray(),
            'period' => ['start' => $start, 'end' => $end],
            'transactions' => $transactions->toArray(),
            'total_spent' => $totalSpent,
            'processed_replenishments' => $processedReplenishments,
            'transaction_count' => $transactions->count(),
            'authorized_amount' => $fund->authorized_amount,
            'expected_balance' => $fund->authorized_amount - $totalSpent + $processedReplenishments,
        ];
    }

    public function checkVarianceThreshold(float $variance, float $authorizedAmount): string
    {
        $percentage = abs($variance) / $authorizedAmount * 100;

        return match (true) {
            $percentage < 0.5 => 'negligible',
            $percentage < 2.0 => 'minor',
            $percentage < 5.0 => 'moderate',
            default => 'critical',
        };
    }
}
