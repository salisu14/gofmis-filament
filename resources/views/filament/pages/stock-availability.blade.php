<x-filament-panels::page>
    @php
        $service = app(\App\Services\Inventory\StockAvailabilityService::class);
        $metrics = $service->getItemStockMetrics();
        $totalItems = $metrics->count();
        $inStock = $metrics->where('status', 'IN_STOCK')->count();
        $lowStock = $metrics->where('status', 'LOW_STOCK')->count();
        $outOfStock = $metrics->where('status', 'OUT_OF_STOCK')->count();
        $totalOnHand = $metrics->sum('on_hand');
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Items</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $totalItems }}</div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-emerald-600 dark:text-emerald-400">In Stock</div>
            <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-300 mt-1">{{ $inStock }}</div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-amber-600 dark:text-amber-400">Low Stock</div>
            <div class="text-2xl font-bold text-amber-700 dark:text-amber-300 mt-1">{{ $lowStock }}</div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-rose-600 dark:text-rose-400">Out of Stock</div>
            <div class="text-2xl font-bold text-rose-700 dark:text-rose-300 mt-1">{{ $outOfStock }}</div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="text-sm font-medium text-blue-600 dark:text-blue-400">Total Units On Hand</div>
            <div class="text-2xl font-bold text-blue-700 dark:text-blue-300 mt-1">{{ number_format($totalOnHand) }}</div>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
