{{-- resources/views/filament/coordinator/widgets/pending-items.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Pending Items Summary
        </x-slot>

        <!-- 4 Compact Statistic Tiles -->
        <div class="coordinator-pending-tiles-grid">
            <div class="coordinator-pending-tile bg-amber-50/80 dark:bg-amber-950/20 border-amber-200 dark:border-amber-900/40">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-amber-800 dark:text-amber-300 truncate">Pending Loans</span>
                    <div class="coordinator-widget-icon-sm bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300"
                         style="width: 1.75rem !important; height: 1.75rem !important; min-width: 1.75rem !important; min-height: 1.75rem !important; flex: 0 0 1.75rem !important; display: flex !important; align-items: center !important; justify-content: center !important;">
                        <x-heroicon-m-banknotes class="coordinator-widget-icon-sm"
                                                 style="width: 0.875rem !important; height: 0.875rem !important; min-width: 0.875rem !important; min-height: 0.875rem !important; flex: none !important;" />
                    </div>
                </div>
                <span class="text-xl font-bold text-amber-900 dark:text-amber-100">{{ data_get($counts, 'loans', 0) }}</span>
            </div>

            <div class="coordinator-pending-tile bg-sky-50/80 dark:bg-sky-950/20 border-sky-200 dark:border-sky-900/40">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-sky-800 dark:text-sky-300 truncate">Pending Education</span>
                    <div class="coordinator-widget-icon-sm bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300"
                         style="width: 1.75rem !important; height: 1.75rem !important; min-width: 1.75rem !important; min-height: 1.75rem !important; flex: 0 0 1.75rem !important; display: flex !important; align-items: center !important; justify-content: center !important;">
                        <x-heroicon-m-academic-cap class="coordinator-widget-icon-sm"
                                                    style="width: 0.875rem !important; height: 0.875rem !important; min-width: 0.875rem !important; min-height: 0.875rem !important; flex: none !important;" />
                    </div>
                </div>
                <span class="text-xl font-bold text-sky-900 dark:text-sky-100">{{ data_get($counts, 'education', 0) }}</span>
            </div>

            <div class="coordinator-pending-tile bg-rose-50/80 dark:bg-rose-950/20 border-rose-200 dark:border-rose-900/40">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-rose-800 dark:text-rose-300 truncate">Recent Healthcare</span>
                    <div class="coordinator-widget-icon-sm bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300"
                         style="width: 1.75rem !important; height: 1.75rem !important; min-width: 1.75rem !important; min-height: 1.75rem !important; flex: 0 0 1.75rem !important; display: flex !important; align-items: center !important; justify-content: center !important;">
                        <x-heroicon-m-heart class="coordinator-widget-icon-sm"
                                            style="width: 0.875rem !important; height: 0.875rem !important; min-width: 0.875rem !important; min-height: 0.875rem !important; flex: none !important;" />
                    </div>
                </div>
                <span class="text-xl font-bold text-rose-900 dark:text-rose-100">{{ data_get($counts, 'healthcare', 0) }}</span>
            </div>

            <div class="coordinator-pending-tile bg-emerald-50/80 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-900/40">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-emerald-800 dark:text-emerald-300 truncate">Pending Welfare</span>
                    <div class="coordinator-widget-icon-sm bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300"
                         style="width: 1.75rem !important; height: 1.75rem !important; min-width: 1.75rem !important; min-height: 1.75rem !important; flex: 0 0 1.75rem !important; display: flex !important; align-items: center !important; justify-content: center !important;">
                        <x-heroicon-m-gift class="coordinator-widget-icon-sm"
                                           style="width: 0.875rem !important; height: 0.875rem !important; min-width: 0.875rem !important; min-height: 0.875rem !important; flex: none !important;" />
                    </div>
                </div>
                <span class="text-xl font-bold text-emerald-900 dark:text-emerald-100">{{ data_get($counts, 'welfare', 0) }}</span>
            </div>
        </div>

        <!-- Recent Pending Items Detail List -->
        <div class="border-t border-gray-100 dark:border-gray-800 pt-2 mt-2">
            <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5 uppercase tracking-wider">Recent Pending</h4>

            <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-56 overflow-y-auto pr-1">
                @forelse($items as $item)
                    <a href="{{ $item['url'] }}" class="coordinator-activity-row">
                        <div class="coordinator-widget-icon-sm rounded bg-{{ $item['color'] }}-100 dark:bg-{{ $item['color'] }}-900/30 text-{{ $item['color'] }}-600"
                             style="width: 1.75rem !important; height: 1.75rem !important; min-width: 1.75rem !important; min-height: 1.75rem !important; flex: 0 0 1.75rem !important; display: flex !important; align-items: center !important; justify-content: center !important;">
                            <x-dynamic-component :component="$item['icon']"
                                                 class="coordinator-widget-icon-sm"
                                                 style="width: 0.875rem !important; height: 0.875rem !important; min-width: 0.875rem !important; min-height: 0.875rem !important; max-width: 0.875rem !important; max-height: 0.875rem !important; flex: none !important;" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-1">
                                <p class="text-xs font-medium text-gray-900 dark:text-white truncate">
                                    {{ $item['name'] }}
                                </p>
                                <span class="text-[10px] font-semibold text-{{ $item['color'] }}-700 dark:text-{{ $item['color'] }}-300 bg-{{ $item['color'] }}-50 dark:bg-{{ $item['color'] }}-900/30 px-1.5 py-0.5 rounded-full shrink-0">
                                    {{ $item['status'] }}
                                </span>
                            </div>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                {{ $item['label'] }} • {{ $item['detail'] }}
                            </p>
                        </div>
                    </a>
                @empty
                    <div class="text-center py-3 text-gray-400 text-xs">
                        No pending items in queue
                    </div>
                @endforelse
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
