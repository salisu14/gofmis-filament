<?php

namespace App\Filament\Widgets;

use App\Services\Inventory\StockAvailabilityService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockAvailabilityStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $service = app(StockAvailabilityService::class);
        $metrics = $service->getItemStockMetrics();

        $totalItems = $metrics->count();
        $inStock = $metrics->where('status', 'IN_STOCK')->count();
        $lowStock = $metrics->where('status', 'LOW_STOCK')->count();
        $outOfStock = $metrics->where('status', 'OUT_OF_STOCK')->count();
        $totalOnHand = $metrics->sum('on_hand');

        return [
            Stat::make('Total Items', $totalItems)
                ->color('gray'),
            Stat::make('In Stock', $inStock)
                ->color('success'),
            Stat::make('Low Stock', $lowStock)
                ->color('warning'),
            Stat::make('Out of Stock', $outOfStock)
                ->color('danger'),
            Stat::make('Total Units On Hand', number_format($totalOnHand))
                ->color('info'),
        ];
    }
}
