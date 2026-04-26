<?php

namespace App\Repositories\Contracts\Imprest;

use App\Models\ImprestReplenishment;
use Illuminate\Support\Collection;

interface ImprestReplenishmentRepositoryInterface
{
    public function create(array $data): ImprestReplenishment;

    public function findById(string $id): ?ImprestReplenishment;

    public function findByFund(string $fundId): Collection;

    public function getPending(): Collection;

    public function approve(string $replenishmentId, string $approvedBy): ImprestReplenishment;

    public function reject(string $replenishmentId, string $rejectedBy, string $reason): ImprestReplenishment;

    public function process(string $replenishmentId): ImprestReplenishment;

    public function getByPeriod(string $fundId, string $start, string $end): ?ImprestReplenishment;
}
