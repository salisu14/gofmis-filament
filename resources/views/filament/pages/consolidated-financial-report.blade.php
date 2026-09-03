<x-filament-panels::page>
    {{-- Report Mode Tabs --}}
    <div class="flex items-center space-x-4 border-b border-gray-200 dark:border-gray-700 pb-3 mb-6">
        <button
            wire:click="setTab('all')"
            class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors {{ $activeTab === 'all' ? 'bg-primary-600 text-white shadow' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 hover:bg-gray-200' }}"
        >
            📊 All Financial Movements
        </button>
        <button
            wire:click="setTab('expenditure_only')"
            class="px-4 py-2 text-sm font-semibold rounded-lg transition-colors {{ $activeTab === 'expenditure_only' ? 'bg-danger-600 text-white shadow' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 hover:bg-gray-200' }}"
        >
            💸 Expenditure Only
        </button>
    </div>

    {{-- Filter Form --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm mb-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Filters</h3>
        {{ $this->form }}
    </div>

    {{-- Native Filament StatsOverviewWidget Cards --}}
    <div class="mb-6">
        @livewire(\App\Filament\Widgets\ConsolidatedFinancialOverviewWidget::class, [
            'filters' => $this->data,
            'activeTab' => $this->activeTab,
        ], key('kpi-overview-widget-'.md5(json_encode($this->data).$this->activeTab)))
    </div>

    {{-- Consolidated Register Table --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
