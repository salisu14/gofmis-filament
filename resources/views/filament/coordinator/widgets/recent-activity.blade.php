{{-- resources/views/filament/coordinator/widgets/recent-activity.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Recent Activity
        </x-slot>

        <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-80 overflow-y-auto pr-1">
            @forelse($activities as $activity)
                <a href="{{ $activity['url'] ?? '#' }}" class="coordinator-activity-row">
                    <div class="coordinator-widget-icon-sm rounded-full bg-{{ $activity['color'] }}-100 dark:bg-{{ $activity['color'] }}-900/30 text-{{ $activity['color'] }}-600 dark:text-{{ $activity['color'] }}-400"
                         style="width: 2rem !important; height: 2rem !important; min-width: 2rem !important; min-height: 2rem !important; flex: 0 0 2rem !important; display: flex !important; align-items: center !important; justify-content: center !important;">
                        <x-dynamic-component :component="$activity['icon']"
                                             class="coordinator-widget-icon-sm"
                                             style="width: 1rem !important; height: 1rem !important; min-width: 1rem !important; min-height: 1rem !important; max-width: 1rem !important; max-height: 1rem !important; flex: none !important;" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-1">
                            <p class="text-xs font-semibold text-gray-900 dark:text-white truncate">
                                {{ $activity['label'] }}
                            </p>
                            <span class="text-[10px] text-gray-400 dark:text-gray-500 shrink-0">
                                {{ $activity['time']->diffForHumans() }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                            {{ $activity['description'] }}
                        </p>
                    </div>
                </a>
            @empty
                <div class="text-center py-6 text-gray-400 dark:text-gray-500">
                    <x-heroicon-m-inbox class="mx-auto mb-1.5 opacity-50 coordinator-widget-icon-sm"
                                       style="width: 1.75rem !important; height: 1.75rem !important; min-width: 1.75rem !important; min-height: 1.75rem !important; max-width: 1.75rem !important; max-height: 1.75rem !important; flex: none !important;" />
                    <p class="text-xs">No recent activity in your zone</p>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
