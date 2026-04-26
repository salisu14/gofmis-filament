<?php

namespace App\Services\Imprest;

use App\Data\Imprest\CreateReplenishmentDto;
use App\Events\Imprest\ReplenishmentApproved;
use App\Events\Imprest\ReplenishmentProcessed;
use App\Events\Imprest\ReplenishmentRequested;
use App\Models\ImprestReplenishment;
use App\Repositories\Contracts\Imprest\ImprestFundRepositoryInterface;
use App\Repositories\Contracts\Imprest\ImprestReplenishmentRepositoryInterface;
use App\Repositories\Contracts\Imprest\ImprestTransactionRepositoryInterface;
use App\Services\Contracts\Imprest\ImprestReplenishmentServiceInterface;
use Illuminate\Support\Facades\DB;

class ImprestReplenishmentService implements ImprestReplenishmentServiceInterface
{
    public function __construct(
        private readonly ImprestReplenishmentRepositoryInterface $replenishmentRepo,
        private readonly ImprestTransactionRepositoryInterface $transactionRepo,
        private readonly ImprestFundRepositoryInterface $fundRepo,
    ) {}

    public function createRequest(CreateReplenishmentDto $dto): ImprestReplenishment
    {
        return DB::transaction(function () use ($dto) {
            $amount = $this->calculateReplenishmentAmount(
                $dto->fundId,
                $dto->periodStart->toDateString(),
                $dto->periodEnd->toDateString()
            );

            $replenishment = $this->replenishmentRepo->create([
                'fund_id' => $dto->fundId,
                'period_start' => $dto->periodStart,
                'period_end' => $dto->periodEnd,
                'amount' => $amount,
                'receipts_total' => $amount,
                'variance' => 0,
                'requested_by' => $dto->requestedBy,
                'status' => 'submitted',
                'notes' => $dto->notes,
            ]);

            event(new ReplenishmentRequested($replenishment));

            return $replenishment;
        });
    }

    public function approve(string $replenishmentId, string $approvedBy): ImprestReplenishment
    {
        return DB::transaction(function () use ($replenishmentId, $approvedBy) {
            $replenishment = $this->replenishmentRepo->approve($replenishmentId, $approvedBy);

            event(new ReplenishmentApproved($replenishment));

            return $replenishment;
        });
    }

    public function process(string $replenishmentId): ImprestReplenishment
    {
        return DB::transaction(function () use ($replenishmentId) {
            $replenishment = $this->replenishmentRepo->process($replenishmentId);

            $fund = $this->fundRepo->findById($replenishment->fund_id);
            $this->fundRepo->updateBalance(
                $replenishment->fund_id,
                $fund->authorized_amount
            );

            event(new ReplenishmentProcessed($replenishment));

            return $replenishment;
        });
    }

    public function calculateReplenishmentAmount(string $fundId, string $start, string $end): float
    {
        return $this->transactionRepo->getTotalSpentInPeriod($fundId, $start, $end);
    }
}
