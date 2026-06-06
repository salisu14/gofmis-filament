{{-- resources/views/filament/components/company-document-preview.blade.php --}}
<div class="p-6 bg-white rounded-lg border border-gray-200">
    @php
        $addressLines = array_filter([
            $company->address_line_1,
            $company->address_line_2,
            trim("{$company->city}, {$company->state_province} {$company->postal_code}"),
            $company->country?->label(),
        ]);
    @endphp

    <div class="flex items-start gap-4">
        @if($company->logo_url)
            <img
                src="{{ $company->logo_url }}?v={{ optional($company->updated_at)->timestamp }}"
                alt="Logo"
                class="h-16 w-auto object-contain"
                loading="lazy"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
            >
            <div class="h-16 w-24 bg-gray-100 items-center justify-center text-gray-400 text-xs hidden">
                No Logo
            </div>
        @else
            <div class="h-16 w-24 bg-gray-100 flex items-center justify-center text-gray-400 text-xs">
                No Logo
            </div>
        @endif

        <div class="flex-1">
            <h3 class="text-lg font-bold text-gray-900">{{ $company->company_name }}</h3>
            @if($company->trading_name)
                <p class="text-sm text-gray-500">{{ $company->trading_name }}</p>
            @endif

            <div class="mt-2 text-sm text-gray-600">
                @foreach($addressLines as $line)
                    <p>{{ $line }}</p>
                @endforeach
            </div>

            <div class="mt-2 text-sm text-gray-600 flex flex-wrap gap-x-4">
                @if($company->phone_no)
                    <span>Tel: {{ $company->phone_no }}</span>
                @endif
                @if($company->email)
                    <span>Email: {{ $company->email }}</span>
                @endif
                @if($company->website)
                    <span>Web: {{ $company->website }}</span>
                @endif
            </div>

            @if($company->tax_registration_no)
                <p class="mt-1 text-sm text-gray-500">
                    Tax Reg No: {{ $company->tax_registration_no }}
                </p>
            @endif
        </div>
    </div>

    @if($company->invoice_footer)
        <div class="mt-4 pt-4 border-t border-gray-200 text-sm text-gray-500 italic">
            {{ $company->invoice_footer }}
        </div>
    @endif
</div>
