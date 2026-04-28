{{-- resources/views/filament/resources/id-cards/pages/preview-id-card.blade.php --}}
<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="space-y-4">
            <x-filament::section>
                <x-slot name="heading">
                    Card Details
                </x-slot>

                <dl class="grid grid-cols-1 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Card Number</dt>
                        <dd class="mt-1 text-lg font-semibold">{{ $record->card_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Beneficiary</dt>
                        <dd class="mt-1">{{ $record->cardable->full_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Type</dt>
                        <dd class="mt-1">{{ class_basename($record->cardable_type) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <x-filament::badge :color="match($record->status) {
                                'draft' => 'gray',
                                'active' => 'success',
                                'revoked' => 'danger',
                                'expired' => 'warning',
                            }">
                                {{ ucfirst($record->status) }}
                            </x-filament::badge>
                        </dd>
                    </div>
                </dl>
            </x-filament::section>
        </div>

        <div>
            <x-filament::section>
                <x-slot name="heading">
                    PDF Preview
                </x-slot>

                <div class="aspect-[1.586] w-full bg-gray-100 rounded-lg overflow-hidden">
                    <iframe
                        src="{{ $pdf_url }}"
                        class="w-full h-full min-h-[500px]"
                        type="application/pdf">
                    </iframe>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
