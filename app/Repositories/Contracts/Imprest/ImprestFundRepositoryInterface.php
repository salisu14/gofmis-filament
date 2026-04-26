<?php

namespace App\Repositories\Contracts\Imprest;

use App\Models\ImprestFund;
use Illuminate\Support\Collection;

interface ImprestFundRepositoryInterface
{
    public function create(array $data): ImprestFund;

    public function findById(string $id): ?ImprestFund;

    public function findByCustodian(string $custodianId): ?ImprestFund;

    public function getAllActive(): Collection;

    public function updateBalance(string $fundId, float $amount): ImprestFund;

    public function updateLastReconciled(string $fundId): ImprestFund;

    public function suspend(string $fundId, string $reason): ImprestFund;
}
