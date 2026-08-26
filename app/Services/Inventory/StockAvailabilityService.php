<?php

namespace App\Services\Inventory;

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\WelfarePackage;
use App\Models\WelfarePackageItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockAvailabilityService
{
    /**
     * Get stock availability metrics for all items or a specific item.
     *
     * On Hand  = SUM(stock_movements.quantity) for item
     * Reserved = SUM(quantity_per_family) for approved, uncollected welfare allocations
     * Available = max(0, On Hand - Reserved)
     */
    public function getItemStockMetrics(?string $itemId = null): Collection
    {
        $query = Item::with('category');

        if ($itemId) {
            $query->where('id', $itemId);
        }

        $items = $query->get();

        // On Hand from canonical stock_movements ledger (grouped aggregate)
        $onHandByItem = StockMovement::select('item_id', DB::raw('COALESCE(SUM(quantity), 0) as on_hand'))
            ->groupBy('item_id')
            ->pluck('on_hand', 'item_id');

        // Reserved = approved + NOT_COLLECTED welfare allocations × quantity_per_family
        $reservedByItem = WelfarePackageItem::join(
            'welfare_beneficiaries',
            'welfare_package_items.welfare_package_id',
            '=',
            'welfare_beneficiaries.welfare_package_id'
        )
            ->where('welfare_beneficiaries.status', BeneficiaryStatus::APPROVED->value)
            ->where('welfare_beneficiaries.collection_status', CollectionStatus::NOT_COLLECTED->value)
            ->whereNull('welfare_beneficiaries.deleted_at')
            ->select('welfare_package_items.item_id', DB::raw('COALESCE(SUM(welfare_package_items.quantity_per_family), 0) as total_reserved'))
            ->groupBy('welfare_package_items.item_id')
            ->pluck('total_reserved', 'welfare_package_items.item_id');

        return $items->map(function (Item $item) use ($onHandByItem, $reservedByItem) {
            $onHand = (int) ($onHandByItem->get($item->id) ?? 0);
            $reserved = (int) ($reservedByItem->get($item->id) ?? 0);
            $available = max(0, $onHand - $reserved);
            $reorderLevel = $item->reorder_level ?? 15;

            $status = match (true) {
                $available <= 0 => 'OUT_OF_STOCK',
                $available <= $reorderLevel => 'LOW_STOCK',
                default => 'IN_STOCK',
            };

            return [
                'item_id' => $item->id,
                'name' => $item->name,
                'category_name' => $item->category?->name ?? 'Uncategorized',
                'unit_of_measure' => $item->unit_of_measure ?? 'Units',
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'available' => $available,
                'status' => $status,
                'reorder_level' => $reorderLevel,
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
