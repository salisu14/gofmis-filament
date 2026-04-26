<?php

namespace App\Services\Contracts\Imprest;

use App\Data\Imprest\CreateReplenishmentDto;
use App\Models\ImprestReplenishment;

interface ImprestReplenishmentServiceInterface
{
    public function createRequest(CreateReplenishmentDto $dto): ImprestReplenishment;

    public function approve(string $replenishmentId, string $approvedBy): ImprestReplenishment;

    public function process(string $replenishmentId): ImprestReplenishment;

    public function calculateReplenishmentAmount(string $fundId, string $start, string $end): float;
}
