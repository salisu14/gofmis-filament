<?php

namespace App\Repositories\Contracts\Imprest;

use App\Data\Imprest\ReconcileFundDto;
use App\Models\ImprestReconciliation;
use Illuminate\Support\Collection;

interface ImprestReconciliationRepositoryInterface
{
    public function create(ReconcileFundDto $dto): ImprestReconciliation;

    public function findById(string $id): ?ImprestReconciliation;

    public function getByFund(string $fundId): Collection;

    public function getLatestByFund(string $fundId): ?ImprestReconciliation;

    public function complete(string $reconciliationId): ImprestReconciliation;

    public function flag(string $reconciliationId, string $reason): ImprestReconciliation;
}
