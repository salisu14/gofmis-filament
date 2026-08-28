<x-filament-panels::page>
    @php
        $metrics = $this->getSummaryMetrics();
        $startDate = $this->data['start_date'] ?? now()->startOfYear()->toDateString();
        $endDate = $this->data['end_date'] ?? now()->toDateString();
        $status = $this->data['status'] ?? '';

        $queryParams = array_filter([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
        ]);

        $previewUrl = route('reports.project-report.pdf', array_merge($queryParams, ['action' => 'preview']));
        $downloadUrl = route('reports.project-report.pdf', array_merge($queryParams, ['action' => 'download']));
    @endphp

    <div class="space-y-6">
        <form wire:submit.prevent="$refresh">
            {{ $this->form }}
        </form>

        <div class="flex justify-end gap-3">
            <a href="{{ $previewUrl }}" target="_blank" class="fi-btn fi-btn-color-info fi-btn-size-md inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-white bg-info-600 hover:bg-info-500 shadow-sm transition">
                <x-heroicon-m-eye class="w-4 h-4" />
                Preview PDF Report
            </a>
            <a href="{{ $downloadUrl }}" class="fi-btn fi-btn-color-primary fi-btn-size-md inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-500 shadow-sm transition">
                <x-heroicon-m-arrow-down-tray class="w-4 h-4" />
                Download PDF Report
            </a>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Projects</div>
                <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($metrics['total_projects']) }}</div>
                <div class="mt-1 text-xs text-gray-500">Completed: {{ number_format($metrics['completed_projects']) }} | Active: {{ number_format($metrics['active_projects']) }}</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Budget</div>
                <div class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₦{{ number_format($metrics['total_budget'], 2) }}</div>
                <div class="mt-1 text-xs text-gray-500">Allocated amount</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Spent</div>
                <div class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">₦{{ number_format($metrics['total_spent'], 2) }}</div>
                <div class="mt-1 text-xs text-gray-500">Disbursed amount</div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Balance / Unspent</div>
                <div class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">₦{{ number_format($metrics['total_budget'] - $metrics['total_spent'], 2) }}</div>
                <div class="mt-1 text-xs text-gray-500">Remaining available</div>
            </div>
        </div>

        <div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
