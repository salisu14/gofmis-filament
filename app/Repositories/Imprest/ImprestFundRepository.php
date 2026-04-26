<?php

namespace App\Repositories\Imprest;

use App\Models\ImprestFund;
use App\Repositories\Contracts\Imprest\ImprestFundRepositoryInterface;
use Illuminate\Support\Collection;

class ImprestFundRepository implements ImprestFundRepositoryInterface
{
    public function create(array $data): ImprestFund
    {
        return ImprestFund::create([
            ...$data,
            'current_balance' => $data['authorized_amount'],
            'status' => 'active',
        ]);
    }

    public function findById(string $id): ?ImprestFund
    {
        return ImprestFund::with(['custodian', 'transactions', 'reconciliations'])
            ->find($id);
    }

    public function findByCustodian(string $custodianId): ?ImprestFund
    {
        return ImprestFund::where('custodian_id', $custodianId)
            ->active()
            ->first();
    }

    public function getAllActive(): Collection
    {
        return ImprestFund::active()
            ->with('custodian')
            ->get();
    }

    public function updateBalance(string $fundId, float $amount): ImprestFund
    {
        $fund = ImprestFund::findOrFail($fundId);
        $fund->update(['current_balance' => $amount]);
        return $fund->fresh();
    }

    public function updateLastReconciled(string $fundId): ImprestFund
    {
        $fund = ImprestFund::findOrFail($fundId);
        $fund->update(['last_reconciled_at' => now()]);
        return $fund->fresh();
    }

    public function suspend(string $fundId, string $reason): ImprestFund
    {
        $fund = ImprestFund::findOrFail($fundId);
        $fund->update([
            'status' => 'suspended',
            'notes' => $fund->notes . "\n[SUSPENDED]: " . $reason,
        ]);
        return $fund->fresh();
    }
}
