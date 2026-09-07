<x-filament-panels::page>
    <div>
        {{-- Filter Form Section --}}
        <div>
            <x-filament::section
                icon="heroicon-o-funnel"
                collapsible
            >
                <x-slot name="heading">
                    Combined Analytics Filters
                </x-slot>

                @can('orphan_education.analytics.export')
                    <x-slot name="headerEnd">
                        <x-filament::button
                            wire:click="exportCsv"
                            icon="heroicon-o-arrow-down-tray"
                            color="success"
                            size="sm"
                        >
                            Export Filtered CSV
                        </x-filament::button>
                    </x-slot>
                @endcan

                {{ $this->filterForm }}
            </x-filament::section>
        </div>

        {{-- KPI Summary Native Filament Widget --}}
        <div style="margin-top: 1.35rem; margin-bottom: 1.35rem;">
            @livewire(\App\Filament\Widgets\EducationAnalyticsOverviewWidget::class, ['filters' => $this->filterData], key('kpi-overview-' . md5(json_encode($this->filterData))))
        </div>

        {{-- Report View Navigation Tabs --}}
        <div>
            <x-filament::tabs>
                <x-filament::tabs.item
                    :active="$activeTab === 'summary'"
                    wire:click="$set('activeTab', 'summary')"
                    icon="heroicon-o-chart-bar"
                >
                    Progression Summary
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'repeated'"
                    wire:click="$set('activeTab', 'repeated')"
                    icon="heroicon-o-arrow-path"
                >
                    Repeated Students
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'graduation'"
                    wire:click="$set('activeTab', 'graduation')"
                    icon="heroicon-o-academic-cap"
                >
                    SS III Graduations
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'transition'"
                    wire:click="$set('activeTab', 'transition')"
                    icon="heroicon-o-arrow-right-circle"
                >
                    P6 → JSS I Transitions
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'institution'"
                    wire:click="$set('activeTab', 'institution')"
                    icon="heroicon-o-building-library"
                >
                    Institution Rates
                </x-filament::tabs.item>

                <x-filament::tabs.item
                    :active="$activeTab === 'lifetime_cost'"
                    wire:click="$set('activeTab', 'lifetime_cost')"
                    icon="heroicon-o-banknotes"
                >
                    Lifetime Support Cost
                </x-filament::tabs.item>
            </x-filament::tabs>
        </div>

        {{-- Active Report Table View --}}
        <div wire:key="table-container-{{ $activeTab }}" style="margin-top: 1rem;">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
