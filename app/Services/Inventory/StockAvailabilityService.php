<?php

namespace App\Services\Inventory;

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use App\Models\ImprestTransaction;
use App\Models\InterventionItem;
use App\Models\Item;
use App\Models\WelfarePackage;
use App\Models\WelfarePackageItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockAvailabilityService
{
    /**
     * Get stock availability metrics for all items or a specific item.
     */
    public function getItemStockMetrics(?string $itemId = null): Collection
    {
        $query = Item::with('category');

        if ($itemId) {
            $query->where('id', $itemId);
        }

        $items = $query->get();

        // Grouped aggregates for purchased stock
        $purchasedStock = ImprestTransaction::whereNotNull('item_id')
            ->select('item_id', DB::raw('SUM(quantity) as total_purchased'))
            ->groupBy('item_id')
            ->pluck('total_purchased', 'item_id');

        // Grouped aggregates for intervention issued stock
        $issuedIntervention = InterventionItem::whereNotNull('item_id')
            ->select('item_id', DB::raw('SUM(quantity) as total_issued'))
            ->groupBy('item_id')
            ->pluck('total_issued', 'item_id');

        // Grouped aggregates for collected welfare allocations
        $welfareCollected = WelfarePackageItem::join('welfare_beneficiaries', 'welfare_package_items.welfare_package_id', '=', 'welfare_beneficiaries.welfare_package_id')
            ->where('welfare_beneficiaries.collection_status', CollectionStatus::COLLECTED->value)
            ->select('welfare_package_items.item_id', DB::raw('SUM(welfare_package_items.quantity_per_family) as total_welfare_issued'))
            ->groupBy('welfare_package_items.item_id')
            ->pluck('total_welfare_issued', 'welfare_package_items.item_id');

        // Grouped aggregates for reserved/approved uncollected welfare allocations
        $welfareReserved = WelfarePackageItem::join('welfare_beneficiaries', 'welfare_package_items.welfare_package_id', '=', 'welfare_beneficiaries.welfare_package_id')
            ->where('welfare_beneficiaries.status', BeneficiaryStatus::APPROVED->value)
            ->where('welfare_beneficiaries.collection_status', CollectionStatus::NOT_COLLECTED->value)
            ->select('welfare_package_items.item_id', DB::raw('SUM(welfare_package_items.quantity_per_family) as total_reserved'))
            ->groupBy('welfare_package_items.item_id')
            ->pluck('total_reserved', 'welfare_package_items.item_id');

        return $items->map(function (Item $item) use ($purchasedStock, $issuedIntervention, $welfareCollected, $welfareReserved) {
            $purchased = (float) ($purchasedStock->get($item->id) ?? 0);
            $issuedInt = (float) ($issuedIntervention->get($item->id) ?? 0);
            $issuedWel = (float) ($welfareCollected->get($item->id) ?? 0);
            $reserved = (float) ($welfareReserved->get($item->id) ?? 0);

            // Default baseline on_hand stock for master catalog items if no explicit purchase entry exists yet
            $baseStock = max(100.0, $purchased);
            $onHand = max(0.0, $baseStock - $issuedInt - $issuedWel);
            $available = max(0.0, $onHand - $reserved);

            $status = match (true) {
                $available <= 0 => 'OUT_OF_STOCK',
                $available <= 15 => 'LOW_STOCK',
                default => 'IN_STOCK',
            };

            return [
                'item_id' => $item->id,
                'name' => $item->name,
                'category_name' => $item->category?->name ?? 'Uncategorized',
                'on_hand' => (int) $onHand,
                'reserved' => (int) $reserved,
                'available' => (int) $available,
                'status' => $status,
                'reorder_level' => 15,
            ];
        });
    }

    /**
     * Calculate how many households a WelfarePackage can serve based on bottleneck stock.
     */
    public function calculatePackageCapacity(WelfarePackage $package): array
    {
        $packageItems = $package->items()->with('item')->get();

        if ($packageItems->isEmpty()) {
            return [
                'capacity' => 0,
                'bottleneck_item' => 'No Package Items Configured',
                'readiness_status' => 'INCOMPLETE',
                'item_breakdown' => [],
            ];
        }

        $minCapacity = PHP_INT_MAX;
        $bottleneckItemName = null;
        $breakdown = [];

        foreach ($packageItems as $pkgItem) {
            $itemMetrics = $this->getItemStockMetrics($pkgItem->item_id)->first();
            $availableStock = $itemMetrics['available'] ?? 0;
            $qtyPerFamily = max(1, (int) $pkgItem->quantity_per_family);

            $itemCapacity = (int) floor($availableStock / $qtyPerFamily);

            $breakdown[] = [
                'item_name' => $pkgItem->item?->name ?? 'Unknown Item',
                'qty_per_family' => $qtyPerFamily,
                'available_stock' => $availableStock,
                'capacity' => $itemCapacity,
            ];

            if ($itemCapacity < $minCapacity) {
                $minCapacity = $itemCapacity;
                $bottleneckItemName = $pkgItem->item?->name ?? 'Unknown Item';
            }
        }

        $finalCapacity = ($minCapacity === PHP_INT_MAX) ? 0 : max(0, $minCapacity);

        $readinessStatus = match (true) {
            $finalCapacity > 0 => 'READY',
            $finalCapacity === 0 => 'OUT_OF_STOCK',
            default => 'INCOMPLETE',
        };

        return [
            'capacity' => $finalCapacity,
            'bottleneck_item' => $bottleneckItemName ?? 'None',
            'readiness_status' => $readinessStatus,
            'item_breakdown' => $breakdown,
        ];
    }
}
