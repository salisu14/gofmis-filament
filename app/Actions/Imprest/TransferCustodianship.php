<?php

namespace App\Actions\Imprest;

use App\Models\ImprestAuditLog;
use App\Models\ImprestFund;
use Illuminate\Support\Facades\DB;

class TransferCustodianship
{
    public function execute(int $fundId, int $newCustodianId, int $witnessId, array $countData): ImprestFund
    {
        return DB::transaction(function () use ($fundId, $newCustodianId, $witnessId, $countData) {
            $fund = ImprestFund::lockForUpdate()->findOrFail($fundId);

            // Record transfer in audit log
            ImprestAuditLog::create([
                'auditable_type' => ImprestFund::class,
                'auditable_id' => $fundId,
                'user_id' => auth()->id(),
                'action' => 'custodian_transferred',
                'old_values' => ['custodian_id' => $fund->custodian_id],
                'new_values' => [
                    'custodian_id' => $newCustodianId,
                    'witness_id' => $witnessId,
                    'cash_count' => $countData,
                ],
                'created_at' => now(),
            ]);

            $fund->update(['custodian_id' => $newCustodianId]);

            return $fund->fresh();
        });
    }
}
