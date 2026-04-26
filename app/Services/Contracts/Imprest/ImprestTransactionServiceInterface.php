<?php

namespace App\Services\Contracts\Imprest;

use App\Data\Imprest\ApproveTransactionDto;
use App\Data\Imprest\CreateTransactionDto;
use App\Data\Imprest\VoidTransactionDto;
use App\Models\ImprestTransaction;

interface ImprestTransactionServiceInterface
{
    public function create(CreateTransactionDto $dto, string $custodianId): ImprestTransaction;

    public function approve(ApproveTransactionDto $dto): ImprestTransaction;

    public function void(VoidTransactionDto $dto): ImprestTransaction;

    public function getFundTransactions(string $fundId, array $filters = []): array;

    public function getDeceasedTransactions(string $deceasedId): array;

    public function validateSpendingLimit(string $fundId, float $amount): bool;
}
