<?php

namespace App\Repositories\Imprest;

use App\Data\Imprest\ReconcileFundDto;
use App\Models\ImprestReconciliation;
use App\Repositories\Contracts\Imprest\ImprestReconciliationRepositoryInterface;
use Illuminate\Support\Collection;

class ImprestReconciliationRepository implements ImprestReconciliationRepositoryInterface
{
    public function create(ReconcileFundDto $dto): ImprestReconciliation
    {
        $expectedBalance = $dto->cashOnHand + $dto->receiptsTotal;
        $variance = $expectedBalance - $this->getAuthorizedAmount($dto->fundId);

        return ImprestReconciliation::create([
            'fund_id' => $dto->fundId,
            'reconciliation_date' => $dto->reconciliationDate,
            'cash_on_hand' => $dto->cashOnHand,
            'receipts_total' => $dto->receiptsTotal,
            'expected_balance' => $expectedBalance,
            'actual_variance' => $variance,
            'auditor_id' => $dto->auditorId,
            'custodian_id' => $dto->custodianId,
            'notes' => $dto->notes,
            'variance_explanation' => $dto->varianceExplanation,
            'status' => abs($variance) < 0.01 ? 'completed' : 'flagged',
        ]);
    }

    public function findById(string $id): ?ImprestReconciliation
    {
        return ImprestReconciliation::with(['fund', 'auditor', 'custodian'])->find($id);
    }

    public function getByFund(string $fundId): Collection
    {
        return ImprestReconciliation::where('fund_id', $fundId)
            ->orderBy('reconciliation_date', 'desc')
            ->get();
    }

    public function getLatestByFund(string $fundId): ?ImprestReconciliation
    {
        return ImprestReconciliation::where('fund_id', $fundId)
            ->orderBy('reconciliation_date', 'desc')
            ->first();
    }

    public function complete(string $reconciliationId): ImprestReconciliation
    {
        $reconciliation = ImprestReconciliation::findOrFail($reconciliationId);
        $reconciliation->update(['status' => 'completed']);
        return $reconciliation->fresh();
    }

    public function flag(string $reconciliationId, string $reason): ImprestReconciliation
    {
        $reconciliation = ImprestReconciliation::findOrFail($reconciliationId);
        $reconciliation->update([
            'status' => 'flagged',
            'variance_explanation' => $reason,
        ]);
        return $reconciliation->fresh();
    }

    private function getAuthorizedAmount(string $fundId): float
    {
        return (float) \App\Models\ImprestFund::findOrFail($fundId)->authorized_amount;
    }
}
