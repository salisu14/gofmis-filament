<?php

namespace App\Listeners;

use App\Events\BeneficiaryCollected;
use Illuminate\Support\Facades\Log;

class LogBeneficiaryCollection
{
    public function handle(BeneficiaryCollected $event): void
    {
        Log::info('Welfare package collected', [
            'beneficiary_id' => $event->beneficiary->id,
            'deceased_id' => $event->beneficiary->deceased_id,
            'package_id' => $event->beneficiary->welfare_package_id,
            'collected_by' => $event->beneficiary->collected_by,
            'collected_at' => $event->beneficiary->collected_at,
            'notes' => $event->notes,
        ]);
    }
}
