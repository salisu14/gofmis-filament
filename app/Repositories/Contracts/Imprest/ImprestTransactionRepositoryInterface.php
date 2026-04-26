<?php

namespace App\Repositories\Contracts\Imprest;

use App\Data\Imprest\CreateTransactionDto;
use App\Models\ImprestTransaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ImprestTransactionRepositoryInterface
{
    public function create(CreateTransactionDto $dto, string $custodianId): ImprestTransaction;

    public function findById(string $id): ?ImprestTransaction;

    public function findByVoucherNo(string $voucherNo): ?ImprestTransaction;

    public function getActiveByFund(string $fundId): Collection;

    public function getPendingByFund(string $fundId): Collection;

    public function getByDeceased(string $deceasedId): Collection;

    public function getInDateRange(string $fundId, string $start, string $end): Collection;

    public function approve(string $transactionId, string $approvedBy): ImprestTransaction;

    public function void(string $transactionId, string $voidedBy, string $reason): ImprestTransaction;

    public function paginateByFund(string $fundId, int $perPage = 15): LengthAwarePaginator;

    public function getTotalSpentInPeriod(string $fundId, string $start, string $end): float;

    public function getMissingReceipts(string $fundId): Collection;
}
