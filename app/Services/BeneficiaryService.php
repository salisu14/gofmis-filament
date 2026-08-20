<?php

namespace App\Services;

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class BeneficiaryService
{
    /**
     * @throws \Throwable
     */
    public function suggestBeneficiary(WelfarePackage $package, string $deceasedId, ?string $suggestedBy = null): WelfareBeneficiary
    {
        if (! $package->isOpen()) {
            throw new RuntimeException('Can only suggest beneficiaries for open packages.');
        }

        if (now()->isAfter($package->end_date)) {
            throw new RuntimeException('This welfare package has ended.');
        }

        if (WelfareBeneficiary::where('welfare_package_id', $package->id)->where('deceased_id', $deceasedId)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'deceased_id' => 'This family already has a welfare request/allocation for the selected package.',
            ]);
        }

        return DB::transaction(function () use ($package, $deceasedId, $suggestedBy) {
            try {
                return WelfareBeneficiary::create([
                    'welfare_package_id' => $package->id,
                    'deceased_id' => $deceasedId,
                    'suggested_by' => $suggestedBy ?? auth()->id(),
                    'status' => BeneficiaryStatus::PENDING,
                    'collection_status' => CollectionStatus::NOT_COLLECTED,
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'unique_package_deceased') || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'deceased_id' => 'This family already has a welfare request/allocation for the selected package.',
                    ]);
                }

                throw $e;
            }
        });
    }

    public function approveBeneficiary(WelfareBeneficiary $beneficiary, ?string $approvedBy = null): WelfareBeneficiary
    {
        return DB::transaction(function () use ($beneficiary, $approvedBy) {
            $locked = WelfareBeneficiary::where('id', $beneficiary->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->canBeApproved()) {
                throw new RuntimeException('This beneficiary cannot be approved.');
            }

            $locked->markAsApproved($approvedBy);

            return $locked->fresh();
        });
    }

    public function rejectBeneficiary(WelfareBeneficiary $beneficiary, string $reason, ?string $rejectedBy = null): WelfareBeneficiary
    {
        if (empty($reason)) {
            throw new InvalidArgumentException('Rejection reason is required.');
        }

        return DB::transaction(function () use ($beneficiary, $reason, $rejectedBy) {
            $locked = WelfareBeneficiary::where('id', $beneficiary->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->canBeRejected()) {
                throw new RuntimeException('This beneficiary cannot be rejected.');
            }

            $locked->markAsRejected($reason, $rejectedBy);

            return $locked->fresh();
        });
    }

    public function collectPackage(WelfareBeneficiary $beneficiary, ?string $notes = null, ?string $collectedBy = null): WelfareBeneficiary
    {
        return DB::transaction(function () use ($beneficiary, $notes, $collectedBy) {
            $locked = WelfareBeneficiary::where('id', $beneficiary->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->canBeCollected()) {
                throw new RuntimeException('This package cannot be collected. Ensure beneficiary is approved and not already collected.');
            }

            $locked->markAsCollected($notes, $collectedBy);

            return $locked->fresh();
        });
    }

    public function bulkApprove(array $beneficiaryIds, ?string $approvedBy = null): int
    {
        return WelfareBeneficiary::whereIn('id', $beneficiaryIds)
            ->pending()
            ->update([
                'status' => BeneficiaryStatus::APPROVED,
                'approved_by' => $approvedBy ?? auth()->id(),
            ]);
    }

    public function bulkCollect(array $beneficiaryIds, ?string $notes = null, ?string $collectedBy = null): int
    {
        $now = now();
        $userId = $collectedBy ?? auth()->id();

        return WelfareBeneficiary::whereIn('id', $beneficiaryIds)
            ->readyForCollection()
            ->update([
                'collection_status' => CollectionStatus::COLLECTED,
                'collected_at' => $now,
                'collected_by' => $userId,
                'collection_notes' => $notes,
            ]);
    }

    public function getBeneficiaryDetails(WelfareBeneficiary $beneficiary): array
    {
        $package = $beneficiary->welfarePackage;
        $deceased = $beneficiary->deceased;

        return [
            'beneficiary' => $beneficiary->toArray(),
            'package_name' => $package->name,
            'package_period' => "{$package->start_date->format('M d, Y')} - {$package->end_date->format('M d, Y')}",
            'deceased_name' => $deceased->name ?? 'N/A',
            'items' => $package->items->map(fn ($item) => [
                'item_name' => $item->item->name ?? 'N/A',
                'category' => $item->category->name ?? 'N/A',
                'quantity' => $item->quantity_per_family,
            ]),
            'suggested_by' => $beneficiary->suggester->name ?? 'N/A',
            'approved_by' => $beneficiary->approver->name ?? 'N/A',
            'collected_by' => $beneficiary->collector->name ?? 'N/A',
            'can_collect' => $beneficiary->canBeCollected(),
            'can_approve' => $beneficiary->canBeApproved(),
            'can_reject' => $beneficiary->canBeRejected(),
        ];
    }
}
