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
        if (!$package->isOpen()) {
            throw new RuntimeException('Can only suggest beneficiaries for open packages.');
        }

        if (now()->isAfter($package->end_date)) {
            throw new RuntimeException('This welfare package has ended.');
        }

        return DB::transaction(function () use ($package, $deceasedId, $suggestedBy) {
            return WelfareBeneficiary::create([
                'welfare_package_id' => $package->id,
                'deceased_id' => $deceasedId,
                'suggested_by' => $suggestedBy ?? auth()->id(),
                'status' => BeneficiaryStatus::PENDING,
                'collection_status' => CollectionStatus::NOT_COLLECTED,
            ]);
        });
    }

    public function approveBeneficiary(WelfareBeneficiary $beneficiary, ?string $approvedBy = null): WelfareBeneficiary
    {
        if (!$beneficiary->canBeApproved()) {
            throw new RuntimeException('This beneficiary cannot be approved.');
        }

        $beneficiary->markAsApproved($approvedBy);

        return $beneficiary->fresh();
    }

    public function rejectBeneficiary(WelfareBeneficiary $beneficiary, string $reason, ?string $rejectedBy = null): WelfareBeneficiary
    {
        if (!$beneficiary->canBeRejected()) {
            throw new RuntimeException('This beneficiary cannot be rejected.');
        }

        if (empty($reason)) {
            throw new InvalidArgumentException('Rejection reason is required.');
        }

        $beneficiary->markAsRejected($reason, $rejectedBy);

        return $beneficiary->fresh();
    }

    public function collectPackage(WelfareBeneficiary $beneficiary, ?string $notes = null, ?string $collectedBy = null): WelfareBeneficiary
    {
        if (!$beneficiary->canBeCollected()) {
            throw new RuntimeException('This package cannot be collected. Ensure beneficiary is approved and not already collected.');
        }

        $beneficiary->markAsCollected($notes, $collectedBy);

        return $beneficiary->fresh();
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
            'items' => $package->items->map(fn($item) => [
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
