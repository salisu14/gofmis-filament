{{-- resources/views/filament/coordinator/widgets/recent-activity.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Recent Activity
        </x-slot>

        <div class="space-y-3 max-h-96 overflow-y-auto">
            @forelse($activities as $activity)
                <a href="{{ $activity['url'] ?? '#' }}"
                   class="flex items-start gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    <div class="flex items-center justify-center flex-shrink-0 p-2 rounded-full bg-{{ $activity['color'] }}-100 dark:bg-{{ $activity['color'] }}-900/20 text-{{ $activity['color'] }}-600 dark:text-{{ $activity['color'] }}-400">
                        <x-dynamic-component :component="$activity['icon']" class="w-4 h-4 shrink-0" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $activity['label'] }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                            {{ $activity['description'] }}
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                            {{ $activity['time']->diffForHumans() }}
                        </p>
                    </div>
                </a>
            @empty
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <x-heroicon-m-inbox class="w-12 h-12 mx-auto mb-3 opacity-50" />
                    <p>No recent activity in your zone</p>
                </div>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
