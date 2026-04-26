<?php

namespace App\Repositories\Imprest;

use App\Models\ImprestReplenishment;
use App\Repositories\Contracts\Imprest\ImprestReplenishmentRepositoryInterface;
use Illuminate\Support\Collection;

class ImprestReplenishmentRepository implements ImprestReplenishmentRepositoryInterface
{
    public function create(array $data): ImprestReplenishment
    {
        return ImprestReplenishment::create($data);
    }

    public function findById(string $id): ?ImprestReplenishment
    {
        return ImprestReplenishment::with(['fund', 'requester', 'approver'])->find($id);
    }

    public function findByFund(string $fundId): Collection
    {
        return ImprestReplenishment::where('fund_id', $fundId)
            ->orderBy('period_start', 'desc')
            ->get();
    }

    public function getPending(): Collection
    {
        return ImprestReplenishment::where('status', 'submitted')
            ->with(['fund', 'requester'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function approve(string $replenishmentId, string $approvedBy): ImprestReplenishment
    {
        $replenishment = ImprestReplenishment::findOrFail($replenishmentId);

        if ($replenishment->status !== 'submitted') {
            throw new \RuntimeException('Only submitted replenishments can be approved');
        }

        $replenishment->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
        ]);

        return $replenishment->fresh();
    }

    public function reject(string $replenishmentId, string $rejectedBy, string $reason): ImprestReplenishment
    {
        $replenishment = ImprestReplenishment::findOrFail($replenishmentId);

        if (!in_array($replenishment->status, ['submitted', 'approved'])) {
            throw new \RuntimeException('Replenishment cannot be rejected');
        }

        $replenishment->update([
            'status' => 'rejected',
            'approved_by' => $rejectedBy,
            'notes' => $replenishment->notes . "\n[REJECTED]: " . $reason,
        ]);

        return $replenishment->fresh();
    }

    public function process(string $replenishmentId): ImprestReplenishment
    {
        $replenishment = ImprestReplenishment::lockForUpdate()->findOrFail($replenishmentId);

        if ($replenishment->status !== 'approved') {
            throw new \RuntimeException('Only approved replenishments can be processed');
        }

        $replenishment->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        return $replenishment->fresh();
    }

    public function getByPeriod(string $fundId, string $start, string $end): ?ImprestReplenishment
    {
        return ImprestReplenishment::where('fund_id', $fundId)
            ->where('period_start', $start)
            ->where('period_end', $end)
            ->first();
    }
}
