<?php

namespace App\Filament\Resources\Items\Widgets;

use App\Models\Item;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ItemStockOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $items = Item::with('stockMovements')->get();
        $totalItems = $items->count();

        $lowStock = 0;
        $totalQuantity = 0;
        $outOfStock = 0;

        foreach ($items as $item) {
            $stock = $item->current_stock;
            $totalQuantity += $stock;

            if ($stock <= 0) {
                $outOfStock++;
            } elseif ($stock <= $item->reorder_level) {
                $lowStock++;
            }
        }

        return [
            Stat::make('Total Items', $totalItems)
                ->description('Total unique items in the catalog')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),
            Stat::make('Total Units Available', $totalQuantity)
                ->description('Total sum of all items in stock')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('success'),
            Stat::make('Low Stock Items', $lowStock)
                ->description('Items at or below reorder level')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStock > 0 ? 'warning' : 'success'),
            Stat::make('Out of Stock Items', $outOfStock)
                ->description('Items with 0 units available')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($outOfStock > 0 ? 'danger' : 'success'),
        ];
    }
}
