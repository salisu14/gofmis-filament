{{-- resources/views/filament/coordinator/widgets/pending-items.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Pending Items Summary
        </x-slot>

        <!-- Count Cards -->
        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="p-3 bg-warning-50 dark:bg-warning-900/20 rounded-lg border border-warning-200 dark:border-warning-800">
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center p-1.5 rounded-full bg-warning-100 dark:bg-warning-900/30">
                        <x-heroicon-m-banknotes class="w-4 h-4 text-warning-600 shrink-0" />
                    </div>
                    <span class="text-2xl font-bold text-warning-700 dark:text-warning-300">{{ $counts['loans'] }}</span>
                </div>
                <p class="text-xs text-warning-600 dark:text-warning-400 mt-1">Pending Loans</p>
            </div>

            <div class="p-3 bg-info-50 dark:bg-info-900/20 rounded-lg border border-info-200 dark:border-info-800">
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center p-1.5 rounded-full bg-info-100 dark:bg-info-900/30">
                        <x-heroicon-m-academic-cap class="w-4 h-4 text-info-600 shrink-0" />
                    </div>
                    <span class="text-2xl font-bold text-info-700 dark:text-info-300">{{ $counts['education'] }}</span>
                </div>
                <p class="text-xs text-info-600 dark:text-info-400 mt-1">Pending Education</p>
            </div>

            <div class="p-3 bg-danger-50 dark:bg-danger-900/20 rounded-lg border border-danger-200 dark:border-danger-800">
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center p-1.5 rounded-full bg-danger-100 dark:bg-danger-900/30">
                        <x-heroicon-m-heart class="w-4 h-4 text-danger-600 shrink-0" />
                    </div>
                    <span class="text-2xl font-bold text-danger-700 dark:text-danger-300">{{ $counts['healthcare'] }}</span>
                </div>
                <p class="text-xs text-danger-600 dark:text-danger-400 mt-1">Recent Healthcare</p>
            </div>

            <div class="p-3 bg-success-50 dark:bg-success-900/20 rounded-lg border border-success-200 dark:border-success-800">
                <div class="flex items-center gap-2">
                    <div class="flex items-center justify-center p-1.5 rounded-full bg-success-100 dark:bg-success-900/30">
                        <x-heroicon-m-gift class="w-4 h-4 text-success-600 shrink-0" />
                    </div>
                    <span class="text-2xl font-bold text-success-700 dark:text-success-300">{{ $counts['welfare'] }}</span>
                </div>
                <p class="text-xs text-success-600 dark:text-success-400 mt-1">Pending Welfare</p>
            </div>
        </div>

        <!-- Recent Pending Items -->
        <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Recent Pending</h4>

            <div class="space-y-2 max-h-64 overflow-y-auto">
                @forelse($items as $item)
                    <a href="{{ $item['url'] }}"
                       class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex items-center justify-center flex-shrink-0 p-1.5 rounded bg-{{ $item['color'] }}-100 dark:bg-{{ $item['color'] }}-900/20 text-{{ $item['color'] }}-600">
                            <x-dynamic-component :component="$item['icon']" class="w-4 h-4 shrink-0" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    {{ $item['name'] }}
                                </p>
                                <span class="text-xs text-{{ $item['color'] }}-600 bg-{{ $item['color'] }}-100 dark:bg-{{ $item['color'] }}-900/20 px-2 py-0.5 rounded-full">
                                    {{ $item['status'] }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $item['label'] }} • {{ $item['detail'] }}
                            </p>
                        </div>
                    </a>
                @empty
                    <div class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm">
                        No pending items
                    </div>
                @endforelse
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
