{{-- resources/views/filament/coordinator/widgets/quick-actions.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Quick Actions
        </x-slot>

        <div class="coordinator-quick-actions-grid">
            @foreach($actions as $action)
                <a href="{{ $action['url'] }}" class="coordinator-quick-action-card group">
                    <div class="coordinator-action-icon-wrapper bg-{{ $action['color'] }}-100 dark:bg-{{ $action['color'] }}-900/30 text-{{ $action['color'] }}-600 dark:text-{{ $action['color'] }}-400"
                         style="width: 2.5rem !important; height: 2.5rem !important; min-width: 2.5rem !important; min-height: 2.5rem !important; flex: 0 0 2.5rem !important; display: flex !important; align-items: center !important; justify-content: center !important;">
                        <x-dynamic-component :component="$action['icon']"
                                             class="coordinator-action-icon"
                                             style="width: 1.25rem !important; height: 1.25rem !important; min-width: 1.25rem !important; min-height: 1.25rem !important; max-width: 1.25rem !important; max-height: 1.25rem !important; flex: none !important;" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                            {{ $action['label'] }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            {{ $action['description'] }}
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
