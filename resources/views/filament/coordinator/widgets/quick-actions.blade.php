{{-- resources/views/filament/coordinator/widgets/quick-actions.blade.php --}}
<x-filament-widgets::widget>
    <div class="grid grid-cols-2 gap-4">
        @foreach($actions as $action)
            <a href="{{ $action['url'] }}"
               class="flex items-center gap-3 p-4 rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-400 transition-colors">
                <div class="flex items-center justify-center p-1.5 rounded-lg bg-{{ $action['color'] }}-100 dark:bg-{{ $action['color'] }}-900/20 text-{{ $action['color'] }}-600 dark:text-{{ $action['color'] }}-400">
                    {{-- Changed to w-4 h-4 and added shrink-0 --}}
                    <x-dynamic-component :component="$action['icon']" class="w-4 h-4 shrink-0" />
                </div>
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">{{ $action['label'] }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $action['description'] }}</p>
                </div>
            </a>
        @endforeach
    </div>
</x-filament-widgets::widget>
