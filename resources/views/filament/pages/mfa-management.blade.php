<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        @foreach ($this->getStats() as $stat)
            <div class="bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-xl p-4 shadow-sm">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider block">
                    {{ $stat['label'] }}
                </span>
                <span class="text-2xl font-bold text-slate-900 dark:text-slate-100 block mt-1">
                    {{ $stat['value'] }}
                </span>
            </div>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
