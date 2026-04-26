<?php

namespace App\Repositories\Imprest;

use App\Data\Imprest\CreateTransactionDto;
use App\Models\ImprestTransaction;
use App\Repositories\Contracts\Imprest\ImprestTransactionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ImprestTransactionRepository implements ImprestTransactionRepositoryInterface
{
    public function create(CreateTransactionDto $dto, string $custodianId): ImprestTransaction
    {
        return DB::transaction(function () use ($dto, $custodianId) {
            $transaction = ImprestTransaction::create([
                ...$dto->toArray(),
                'custodian_id' => $custodianId,
                'status' => 'pending',
            ]);

            return $transaction->fresh(['fund', 'custodian']);
        });
    }

    public function findById(string $id): ?ImprestTransaction
    {
        return ImprestTransaction::with(['fund', 'custodian', 'approver'])->find($id);
    }

    public function findByVoucherNo(string $voucherNo): ?ImprestTransaction
    {
        return ImprestTransaction::with(['fund', 'custodian'])
            ->where('voucher_no', $voucherNo)
            ->first();
    }

    public function getActiveByFund(string $fundId): Collection
    {
        return ImprestTransaction::active()
            ->where('fund_id', $fundId)
            ->orderBy('date', 'desc')
            ->get();
    }

    public function getPendingByFund(string $fundId): Collection
    {
        return ImprestTransaction::pending()
            ->where('fund_id', $fundId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getByDeceased(string $deceasedId): Collection
    {
        return ImprestTransaction::forDeceased($deceasedId)
            ->with('fund')
            ->orderBy('date', 'desc')
            ->get();
    }

    public function getInDateRange(string $fundId, string $start, string $end): Collection
    {
        return ImprestTransaction::where('fund_id', $fundId)
            ->inDateRange($start, $end)
            ->active()
            ->orderBy('date')
            ->get();
    }

    public function approve(string $transactionId, string $approvedBy): ImprestTransaction
    {
        return DB::transaction(function () use ($transactionId, $approvedBy) {
            $transaction = ImprestTransaction::lockForUpdate()->findOrFail($transactionId);

            if (!$transaction->isVoidable()) {
                throw new \RuntimeException('Transaction cannot be approved');
            }

            $transaction->update([
                'status' => 'active',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);

            $transaction->fund()->decrement('current_balance', $transaction->total_price);

            return $transaction->fresh();
        });
    }

    public function void(string $transactionId, string $voidedBy, string $reason): ImprestTransaction
    {
        return DB::transaction(function () use ($transactionId, $voidedBy, $reason) {
            $transaction = ImprestTransaction::lockForUpdate()->findOrFail($transactionId);

            if (!$transaction->isVoidable()) {
                throw new \RuntimeException('Transaction cannot be voided');
            }

            $wasActive = $transaction->status === 'active';

            $transaction->update([
                'status' => 'voided',
                'void_reason' => $reason,
                'voided_at' => now(),
            ]);

            if ($wasActive) {
                $transaction->fund()->increment('current_balance', $transaction->total_price);
            }

            return $transaction->fresh();
        });
    }

    public function paginateByFund(string $fundId, int $perPage = 15): LengthAwarePaginator
    {
        return ImprestTransaction::where('fund_id', $fundId)
            ->with(['custodian', 'approver'])
            ->orderBy('date', 'desc')
            ->paginate($perPage);
    }

    public function getTotalSpentInPeriod(string $fundId, string $start, string $end): float
    {
        return (float) ImprestTransaction::where('fund_id', $fundId)
            ->inDateRange($start, $end)
            ->active()
            ->sum('total_price');
    }

    public function getMissingReceipts(string $fundId): Collection
    {
        return ImprestTransaction::where('fund_id', $fundId)
            ->where('receipt_attached', false)
            ->where('status', '!=', 'voided')
            ->where('created_at', '<', now()->subHours(48))
            ->get();
    }
}
