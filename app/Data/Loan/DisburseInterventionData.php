<?php

namespace App\Data\Loan;

use Spatie\LaravelData\Data;

readonly class DisburseInterventionData
{
    public function __construct(
        public string $interventionRequestId, // Link to the approved request
        public string $interventionTypeId, // e.g., Medical, Education
        public array $items, // Array of ['item_name' => 'Book', 'quantity' => 5, 'unit_value' => 100.00]
        public string $orphanId,
        public ?string $bankAccountId = null,
        public ?float $amount = null,
        public ?string $collectedBy = null,
        public ?string $supportDocUrl = null
    ) {}
}
