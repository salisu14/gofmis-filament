<?php

namespace App\Services\Imprest;

use App\Data\Imprest\ApproveTransactionDto;
use App\Data\Imprest\CreateTransactionDto;
use App\Data\Imprest\VoidTransactionDto;
use App\Events\Imprest\TransactionApproved;
use App\Events\Imprest\TransactionCreated;
use App\Events\Imprest\TransactionVoided;
use App\Exceptions\Imprest\InsufficientFundsException;
use App\Exceptions\Imprest\TransactionNotVoidableException;
use App\Models\ImprestTransaction;
use App\Repositories\Contracts\Imprest\ImprestFundRepositoryInterface;
use App\Repositories\Contracts\Imprest\ImprestTransactionRepositoryInterface;
use App\Services\Contracts\Imprest\ImprestTransactionServiceInterface;
use Illuminate\Support\Facades\DB;

readonly class ImprestTransactionService implements ImprestTransactionServiceInterface
{
    public function __construct(
        private ImprestTransactionRepositoryInterface $transactionRepo,
        private ImprestFundRepositoryInterface        $fundRepo,
        private float                                 $spendingLimit = 100.00,
    ) {}

    /**
     * @throws \Throwable
     */
    public function create(CreateTransactionDto $dto, string $custodianId): ImprestTransaction
    {
        return DB::transaction(function () use ($dto, $custodianId) {
            $fund = $this->fundRepo->findById($dto->fundId);

            if (!$fund || $fund->status !== 'active') {
                throw new \RuntimeException('Fund is not active');
            }

            // Bypass custodian check for super admin
            $user = auth()->user();
            $isSuperAdmin = $user->hasRole('super_admin') || $user->hasPermissionTo('imprest.manage_all');

            if (!$isSuperAdmin && $fund->custodian_id !== $custodianId) {
                throw new \RuntimeException('You are not the custodian of this fund.');
            }

            $totalPrice = $dto->quantity * $dto->unitPrice;

            if (!$this->validateSpendingLimit($dto->fundId, $totalPrice)) {
                throw new \RuntimeException('Transaction exceeds spending limit. Use AP process instead.');
            }

            if ($fund->current_balance < $totalPrice) {
                throw new InsufficientFundsException(
                    "Insufficient funds. Available: {$fund->current_balance}, Required: {$totalPrice}"
                );
            }

            // Use fund's custodian_id if super admin, otherwise use logged-in user
            $transactionCustodianId = $isSuperAdmin ? $fund->custodian_id : $custodianId;

            $transaction = $this->transactionRepo->create($dto, $transactionCustodianId);

            event(new TransactionCreated($transaction));

            return $transaction;
        });
    }

    /**
     * @throws \Throwable
     */
    public function approve(ApproveTransactionDto $dto): ImprestTransaction
    {
        return DB::transaction(function () use ($dto) {
            $transaction = $this->transactionRepo->approve($dto->transactionId, $dto->approvedBy);

            event(new TransactionApproved($transaction));

            return $transaction;
        });
    }

    /**
     * @throws \Throwable
     */
    public function void(VoidTransactionDto $dto): ImprestTransaction
    {
        return DB::transaction(function () use ($dto) {
            $transaction = $this->transactionRepo->findById($dto->transactionId);

            if (!$transaction || !$transaction->isVoidable()) {
                throw new TransactionNotVoidableException('Transaction cannot be voided');
            }

            $result = $this->transactionRepo->void(
                $dto->transactionId,
                $dto->voidedBy,
                $dto->reason
            );

            event(new TransactionVoided($result));

            return $result;
        });
    }

    public function getFundTransactions(string $fundId, array $filters = []): array
    {
        $query = $this->transactionRepo->getActiveByFund($fundId);

        if (!empty($filters['deceased_id'])) {
            $query = $query->where('deceased_id', $filters['deceased_id']);
        }

        if (!empty($filters['category'])) {
            $query = $query->where('category', $filters['category']);
        }

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query = $query->whereBetween('date', [$filters['date_from'], $filters['date_to']]);
        }

        return $query->toArray();
    }

    public function getDeceasedTransactions(string $deceasedId): array
    {
        return $this->transactionRepo->getByDeceased($deceasedId)->toArray();
    }

    public function validateSpendingLimit(string $fundId, float $amount): bool
    {
        return $amount <= $this->spendingLimit;
    }
}
