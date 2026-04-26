<?php

namespace App\Data\Imprest;

readonly class ApproveTransactionDto
{
    public function __construct(
        public string $transactionId,
        public string $approvedBy,
    ) {}
}
