<?php

namespace App\Services\Contracts\Imprest;

use App\Data\Imprest\ReconcileFundDto;
use App\Models\ImprestReconciliation;

interface ImprestReconciliationServiceInterface
{
    public function reconcile(ReconcileFundDto $dto): ImprestReconciliation;

    public function acknowledge(string $reconciliationId, string $custodianId): ImprestReconciliation;

    public function getReconciliationReport(string $fundId, string $start, string $end): array;

    public function checkVarianceThreshold(float $variance, float $authorizedAmount): string;
}
