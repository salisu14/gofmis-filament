<?php

namespace App\Events;

use App\Models\WelfareBeneficiary;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BeneficiaryCollected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly WelfareBeneficiary $beneficiary,
        public readonly ?string $notes = null
    ) {}
}
