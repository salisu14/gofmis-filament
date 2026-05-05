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

                <div class="w-full bg-gray-100 rounded-lg overflow-hidden p-6">
                    @if(!empty($preview_error))
                        <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded">
                            <strong>Preview error:</strong>
                            <div class="mt-2">{{ $preview_error }}</div>
                            <div class="mt-2 text-sm text-gray-500">Check storage/logs/laravel.log for full trace.</div>
                        </div>
                    @else
                        <div class="mb-4">
                            <iframe
                                src="{{ route('id-cards.preview', ['card' => $record]) }}"
                                class="w-full h-[520px] rounded border"
                                type="application/pdf"
                                title="ID Card PDF Preview">
                            </iframe>
                        </div>
                        <div class="flex items-center justify-center">
                            <div style="transform: scale(1); transform-origin: top left;">
                                @include('id-cards.card-content', [
                                    'foundation_logo' => $foundation_logo ?? null,
                                    'foundation_name' => $foundation_name ?? 'Garko Orphans Foundation',
                                    'card_type' => $card_type ?? 'ID CARD',
                                    'card_number' => $card_number ?? ($record->card_number ?? 'N/A'),
                                    'photo_url' => $photo_url ?? null,
                                    'full_name' => $full_name ?? optional($record->cardable)->full_name ?? 'N/A',
                                    'nin' => $nin ?? 'N/A',
                                    'reg_no' => $reg_no ?? optional($record->cardable)->reg_no ?? 'N/A',
                                    'gender' => $gender ?? 'N/A',
                                    'zone' => $zone ?? 'N/A',
                                    'coordinator_name' => $coordinator_name ?? 'N/A',
                                    'coordinator_phone' => $coordinator_phone ?? 'N/A',
                                    'issue_date' => $issue_date ?? now()->format('M d, Y'),
                                    'expiry_date' => $expiry_date ?? null,
                                    'background_color' => $background_color ?? '#ffffff',
                                    'accent_color' => $accent_color ?? '#333333',
                                    'qr_code' => $qr_code ?? null,
                                ])
                            </div>
                        </div>
                    @endif
                    <div class="mt-4 text-sm text-gray-600">If the inline preview doesn't appear, open <a href="{{ $pdf_url }}" target="_blank" class="text-primary-600">the PDF in a new tab</a>.</div>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
