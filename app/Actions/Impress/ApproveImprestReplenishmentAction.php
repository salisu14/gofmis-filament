<?php

namespace App\Actions\Impress;

use App\Models\ImprestReplenishment;
use Illuminate\Support\Facades\DB;

class ApproveImprestReplenishmentAction
{
    /**
     * @throws \Throwable
     */
    public function execute(ImprestReplenishment $replenishment): void
    {
        DB::transaction(function () use ($replenishment) {

            $imprest = $replenishment->imprest;

            $replenishment->update([
                'amount_approved' => $replenishment->amount_requested,
                'approved_at' => now(),
            ]);

            // Restore fund
            $imprest->increment('current_balance', $replenishment->amount_approved);

            // 🔥 Optional: create ledger entry here
        });
    }
}
