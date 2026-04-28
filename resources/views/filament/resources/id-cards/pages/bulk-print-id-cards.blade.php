{{-- resources/views/filament/resources/id-cards/pages/bulk-print-id-cards.blade.php --}}
<x-filament-panels::page>
    <form wire:submit="createBatch">
        {{ $this->form }}

        <div class="mt-6">
            {{ $this->getFormActions()[0] }}
        </div>
    </form>

    @if($currentBatch)
        <x-filament::section class="mt-8">
            <x-slot name="heading">
                Batch Progress: {{ $currentBatch->batch_name }}
            </x-slot>

            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">
                        Status:
                        <x-filament::badge :color="match($currentBatch->status) {
                            'pending' => 'gray',
                            'processing' => 'warning',
                            'completed' => 'success',
                            'failed' => 'danger',
                        }">
                            {{ ucfirst($currentBatch->status) }}
                        </x-filament::badge>
                    </span>

                    <span class="text-sm text-gray-500">
                        {{ number_format($currentBatch->processed_count) }} / {{ number_format($currentBatch->total_count) }} cards
                    </span>
                </div>

                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div
                        class="bg-primary-600 h-2.5 rounded-full transition-all duration-500"
                        style="width: {{ $currentBatch->progressPercentage() }}%"
                    ></div>
                </div>

                @if($currentBatch->status === 'completed' && $currentBatch->pdf_path)
                    <div class="flex gap-4 mt-4">
                        <x-filament::button
                            tag="a"
                            :href="route('id-card-print-batches.download', ['record' => $currentBatch, 'preview' => 1])"
                            target="_blank"
                            icon="heroicon-m-eye"
                        >
                            Preview PDF
                        </x-filament::button>

                        <x-filament::button
                            tag="a"
                            :href="route('id-card-print-batches.download', ['record' => $currentBatch])"
                            icon="heroicon-m-arrow-down-tray"
                            color="success"
                        >
                            Download PDF
                        </x-filament::button>
                    </div>
                @endif

                @if($currentBatch->status === 'processing')
                    <div wire:poll.3s="refreshBatch" class="text-sm text-gray-500 animate-pulse">
                        Processing... please wait
                    </div>
                @endif
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
