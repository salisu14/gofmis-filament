<x-filament-panels::page>
    {{-- Use <form> instead of <x-filament-panels::form> --}}
    <form wire:submit="generateReport" class="fi-form">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" icon="heroicon-m-arrow-path">
                Generate Report
            </x-filament::button>
        </div>
    </form>

    <div class="mt-6">
        {{ $this->table }}
    </div>

    @if(isset($this->data['fund_id']))
        @php
            $service = app(\App\Services\Contracts\Imprest\ImprestReconciliationServiceInterface::class);
            $report = $service->getReconciliationReport(
                $this->data['fund_id'],
                $this->data['start_date'],
                $this->data['end_date']
            );
        @endphp

        <x-filament::section class="mt-6" heading="Summary">
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Total Spent</p>
                    <p class="text-2xl font-bold">${{ number_format($report['total_spent'], 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Transactions</p>
                    <p class="text-2xl font-bold">{{ $report['transaction_count'] }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Authorized</p>
                    <p class="text-2xl font-bold">${{ number_format($report['authorized_amount'], 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Expected Balance</p>
                    <p class="text-2xl font-bold">${{ number_format($report['expected_balance'], 2) }}</p>
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
