<div class="p-6 bg-white text-gray-800 font-sans border border-gray-200 rounded-lg shadow-sm max-w-2xl mx-auto print:shadow-none print:border-none print:p-0">
    <!-- Header -->
    <div class="flex justify-between items-start border-b border-gray-300 pb-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold uppercase tracking-tight text-primary-600">Loan Repayment Receipt</h1>
            <p class="text-sm text-gray-500 italic">Garko Orphans Foundation — Widow Support Program</p>
        </div>
        <div class="text-right">
            <p class="text-sm font-semibold">
                @if($record->receipt_number)
                    Receipt No: <span class="font-mono">RCP-{{ str_pad($record->receipt_number, 5, '0', STR_PAD_LEFT) }}</span>
                @else
                    Ref: {{ $record->transaction?->reference ?? 'N/A' }}
                @endif
            </p>
            <p class="text-xs text-gray-400 uppercase tracking-widest">Date: {{ $record->paid_at->format('d M, Y') }}</p>
        </div>
    </div>

    <!-- Beneficiary Details -->
    <div class="grid grid-cols-2 gap-4 mb-8 bg-gray-50 p-4 rounded-md">
        <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase mb-1">Beneficiary</h3>
            <p class="text-lg font-bold text-gray-900">{{ $widow->full_name }}</p>
            <p class="text-sm text-gray-600 font-mono">Reg No: {{ $widow->reg_no ?? 'N/A' }}</p>
            @if($widow->deceased?->zone)
                <p class="text-xs text-gray-400 mt-1">Zone: {{ $widow->deceased->zone->name }}</p>
            @endif
        </div>
        <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase mb-1">Loan Context</h3>
            <p class="text-sm font-medium">{{ $record->widowLoan->purpose }}</p>
            <p class="text-sm text-gray-500 italic">Status: {{ $record->widowLoan->status->getLabel() }}</p>
            <p class="text-xs text-gray-400 mt-1">
                Frequency: {{ $record->widowLoan->repayment_frequency->getLabel() }}
            </p>
        </div>
    </div>

    <!-- Payment Details Table -->
    <table class="w-full mb-8 text-left">
        <thead>
        <tr class="border-b-2 border-gray-200">
            <th class="py-2 text-xs font-bold uppercase text-gray-600">Description</th>
            <th class="py-2 text-xs font-bold uppercase text-gray-600">Method</th>
            <th class="py-2 text-right text-xs font-bold uppercase text-gray-600">Amount</th>
        </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <tr>
            <td class="py-4">
                {{-- Dynamic label based on repayment frequency --}}
                <p class="font-semibold text-gray-800">
                    {{ $record->widowLoan->repayment_frequency->getLabel() }} Repayment Instalment
                </p>
                @if($record->notes)
                    <span class="text-xs text-gray-400 italic">Note: {{ $record->notes }}</span>
                @endif
            </td>
            <td class="py-4 align-top">
                <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">
                    {{ ucfirst($record->payment_method) }}
                </span>
            </td>
            <td class="py-4 text-right align-top font-bold text-lg text-gray-900">
                ₦{{ number_format($record->amount, 2) }}
            </td>
        </tr>
        </tbody>
    </table>

    <!-- Financial Summary -->
    <div class="flex justify-end mb-12">
        <div class="w-1/2 space-y-2">
            <div class="flex justify-between text-sm text-gray-500">
                <span>Principal Amount:</span>
                <span>₦{{ number_format($record->widowLoan->principal_amount, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm text-gray-500">
                <span>Total Payable:</span>
                <span>₦{{ number_format($record->widowLoan->total_payable, 2) }}</span>
            </div>
            <div class="flex justify-between text-sm text-gray-500">
                <span>Total Paid to Date:</span>
                <span>₦{{ number_format($record->widowLoan->repayments()->where('paid_at', '<=', $record->paid_at)->sum('amount'), 2) }}</span>
            </div>
            <div class="flex justify-between border-t border-gray-200 pt-2 text-lg font-bold text-gray-900">
                <span>Outstanding Balance:</span>
                <span class="{{ $balance > 0 ? 'text-red-600' : 'text-green-600' }}">
                    ₦{{ number_format($balance, 2) }}
                </span>
            </div>
        </div>
    </div>

    <!-- Footer / Signature -->
    <div class="flex justify-between items-end border-t-2 border-dashed border-gray-100 pt-8">
        <div class="text-xs text-gray-400 max-w-xs italic">
            This is a computer-generated receipt. Please keep it safe as proof of your repayment toward financial independence.
        </div>
        <div class="text-center border-t border-gray-900 w-48 pt-2">
            <p class="text-xs font-bold uppercase tracking-widest text-gray-900">Authorized Signature</p>
            <p class="text-[10px] text-gray-400">Finance &amp; Empowerment Dept.</p>
        </div>
    </div>
</div>
