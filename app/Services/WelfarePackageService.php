<?php

namespace App\Services;

use App\Enums\WelfarePackageStatus;
use App\Models\WelfarePackage;
use App\Models\WelfarePackageItem;
use App\Models\WelfareBeneficiary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class WelfarePackageService
{
    /**
     * @throws \Throwable
     */
    public function createPackage(array $data, array $items = []): WelfarePackage
    {
        return DB::transaction(function () use ($data, $items) {
            $package = WelfarePackage::create($data);

            if (!empty($items)) {
                $this->syncItems($package, $items);
            }

            return $package->fresh(['items', 'items.item', 'items.category']);
        });
    }

    /**
     * @throws \Throwable
     */
    public function updatePackage(WelfarePackage $package, array $data, array $items = []): WelfarePackage
    {
        if (!$package->isDraft()) {
            throw new RuntimeException('Only draft packages can be edited.');
        }

        return DB::transaction(function () use ($package, $data, $items) {
            $package->update($data);

            if (!empty($items)) {
                $this->syncItems($package, $items);
            }

            return $package->fresh(['items', 'items.item', 'items.category']);
        });
    }

    public function openPackage(WelfarePackage $package): WelfarePackage
    {
        if (!$package->canBeOpened()) {
            throw new RuntimeException("Cannot open package with status: {$package->status->value}");
        }

        $package->update([
            'status' => WelfarePackageStatus::OPEN,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $package->fresh();
    }

    public function closePackage(WelfarePackage $package): WelfarePackage
    {
        if (!$package->canBeClosed()) {
            throw new RuntimeException("Cannot close package with status: {$package->status->value}");
        }

        $package->update([
            'status' => WelfarePackageStatus::CLOSED,
        ]);

        return $package->fresh();
    }

    public function reopenPackage(WelfarePackage $package): WelfarePackage
    {
        if (!$package->canBeReopened()) {
            throw new RuntimeException('Only closed packages can be reopened.');
        }

        $package->update([
            'status' => WelfarePackageStatus::OPEN,
        ]);

        return $package->fresh();
    }

    /**
     * @throws \Throwable
     */
    public function duplicatePackage(WelfarePackage $package, string $newName, ?\DateTime $newStartDate = null, ?\DateTime $newEndDate = null): WelfarePackage
    {
        return DB::transaction(function () use ($package, $newName, $newStartDate, $newEndDate) {
            $newPackage = $package->replicate([
                'approved_by',
                'approved_at',
            ]);

            $newPackage->fill([
                'name' => $newName,
                'status' => WelfarePackageStatus::DRAFT,
                'created_by' => auth()->id(),
                'start_date' => $newStartDate ?? now(),
                'end_date' => $newEndDate ?? now()->addMonth(),
            ]);

            $newPackage->save();

            // Duplicate items
            foreach ($package->items as $item) {
                WelfarePackageItem::create([
                    'welfare_package_id' => $newPackage->id,
                    'item_id' => $item->item_id,
                    'category_id' => $item->category_id,
                    'quantity_per_family' => $item->quantity_per_family,
                    'notes' => $item->notes,
                ]);
            }

            return $newPackage->fresh(['items']);
        });
    }

    public function syncItems(WelfarePackage $package, array $items): void
    {
        if (!$package->isDraft()) {
            throw new RuntimeException('Items can only be modified for draft packages.');
        }

        // Delete existing items not in the new list
        $incomingIds = collect($items)->pluck('item_id')->filter()->toArray();
        $package->items()->whereNotIn('item_id', $incomingIds)->delete();

        foreach ($items as $itemData) {
            WelfarePackageItem::updateOrCreate(
                [
                    'welfare_package_id' => $package->id,
                    'item_id' => $itemData['item_id'],
                    'category_id' => $itemData['category_id'],
                ],
                [
                    'quantity_per_family' => $itemData['quantity_per_family'] ?? 1,
                    'notes' => $itemData['notes'] ?? null,
                ]
            );
        }
    }

    public function getPackageStatistics(WelfarePackage $package): array
    {
        $beneficiaries = $package->beneficiaries;

        return [
            'total_beneficiaries' => $beneficiaries->count(),
            'pending_approval' => $beneficiaries->where('status', \App\Enums\BeneficiaryStatus::PENDING)->count(),
            'approved' => $beneficiaries->where('status', \App\Enums\BeneficiaryStatus::APPROVED)->count(),
            'rejected' => $beneficiaries->where('status', \App\Enums\BeneficiaryStatus::REJECTED)->count(),
            'collected' => $beneficiaries->where('collection_status', \App\Enums\CollectionStatus::COLLECTED)->count(),
            'not_collected' => $beneficiaries->where('collection_status', \App\Enums\CollectionStatus::NOT_COLLECTED)
                ->where('status', \App\Enums\BeneficiaryStatus::APPROVED)->count(),
            'collection_rate' => $beneficiaries->where('status', \App\Enums\BeneficiaryStatus::APPROVED)->count() > 0
                ? round(
                    ($beneficiaries->where('collection_status', \App\Enums\CollectionStatus::COLLECTED)->count() /
                        $beneficiaries->where('status', \App\Enums\BeneficiaryStatus::APPROVED)->count()) * 100,
                    2
                )
                : 0,
            'total_items' => $package->items->count(),
            'total_quantity' => $package->items->sum('quantity_per_family'),
        ];
    }

    public function exportBeneficiaries(WelfarePackage $package): Collection
    {
        return $package->beneficiaries()
            ->with(['deceased', 'suggester', 'approver', 'collector'])
            ->get()
            ->map(function ($beneficiary) {
                return [
                    'deceased_name' => $beneficiary->deceased->name ?? 'N/A',
                    'suggested_by' => $beneficiary->suggester->name ?? 'N/A',
                    'status' => $beneficiary->status->label(),
                    'collection_status' => $beneficiary->collection_status->label(),
                    'collected_at' => $beneficiary->collected_at?->format('Y-m-d H:i'),
                    'collected_by' => $beneficiary->collector?->name,
                ];
            });
    }
}
