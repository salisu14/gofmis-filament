<?php

namespace App\Data\Imprest;

readonly class VoidTransactionDto
{
    public function __construct(
        public string $transactionId,
        public string $voidedBy,
        public string $reason,
    ) {}
}
