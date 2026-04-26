<?php

namespace App\Data\Imprest;

use Carbon\Carbon;

readonly class ReconcileFundDto
{
    public function __construct(
        public string $fundId,
        public Carbon $reconciliationDate,
        public float $cashOnHand,
        public float $receiptsTotal,
        public string $auditorId,      // UUID = string
        public string $custodianId,    // UUID = string
        public ?string $notes = null,
        public ?string $varianceExplanation = null,
    ) {}
}
