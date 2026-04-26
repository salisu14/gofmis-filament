<?php

namespace App\Actions\Impress;

use App\Models\Imprest;
use App\Models\ImprestReplenishment;

class RequestImprestReplenishmentAction
{
    public function execute(Imprest $imprest): ImprestReplenishment
    {
        $amount = $imprest->authorized_amount - $imprest->current_balance;

        return ImprestReplenishment::create([
            'imprest_id' => $imprest->id,
            'amount_requested' => $amount,
            'requested_at' => now(),
        ]);
    }
}
