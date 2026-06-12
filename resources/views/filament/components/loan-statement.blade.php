<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 12px;
            margin: 0;
            padding: 20px;
        }
        .header-table { width: 100%; border-bottom: 2px solid #d1d5db; padding-bottom: 15px; margin-bottom: 20px; }
        .brand-logo { width: 42px; max-height: 42px; object-fit: contain; margin-right: 10px; vertical-align: middle; }
        .brand-copy { display: inline-block; vertical-align: middle; }
        .title { font-size: 20px; font-weight: bold; text-transform: uppercase; letter-spacing: -0.5px; color: #4f46e5; margin: 0; }
        .subtitle { font-size: 11px; color: #6b7280; font-style: italic; margin-top: 5px; }
        .text-right { text-align: right; }
        .text-mono { font-family: 'Courier New', monospace; }
        .text-sm { font-size: 11px; }
        .text-xs { font-size: 10px; }
        .text-2xs { font-size: 9px; }

        .context-box { background-color: #f9fafb; padding: 15px; border-radius: 5px; width: 100%; margin-bottom: 25px; border: 1px solid #e5e7eb; }
        .context-box td { vertical-align: top; width: 50%; }

        .history-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .history-table th { text-align: left; border-bottom: 2px solid #e5e7eb; padding: 8px 4px; font-size: 10px; text-transform: uppercase; color: #4b5563; font-weight: bold; }
        .history-table td { padding: 10px 4px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; font-size: 11px; }
        .history-table .alternate-row { background-color: #f9fafb; }

        /* Fix for currency alignment */
        .currency-cell {
            text-align: right;
            white-space: nowrap; /* Prevents amount from wrapping to next line */
            font-variant-numeric: tabular-nums; /* Ensures numbers take up same width */
        }

        .summary-box { background-color: #f9fafb; padding: 15px; border: 1px solid #e5e7eb; border-radius: 5px; width: 50%; float: right; margin-bottom: 40px; }
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table td { padding: 4px 0; font-size: 12px; color: #6b7280; }
        .summary-table .highlight-row td { font-weight: bold; color: #4f46e5; font-size: 13px; }
        .summary-table .total-row td { border-top: 2px solid #111827; padding-top: 8px; font-size: 16px; font-weight: bold; color: #111827; }
        .text-red { color: #dc2626; }
        .text-green { color: #16a34a; }

        .footer-table { width: 100%; border-top: 2px dashed #e5e7eb; padding-top: 20px; }
    </style>
</head>
<body>
@php($company = $company ?? app(\App\Services\Company\CompanyInformationService::class)->reportHeader())

<!-- Header -->
<table class="header-table">
    <tr>
        <td>
            @if($company['logo_data_uri'] ?? null)
                <img src="{{ $company['logo_data_uri'] }}" class="brand-logo" alt="">
            @endif
            <div class="brand-copy">
                <h1 class="title">Cumulative Repayment Statement</h1>
                <p class="subtitle">{{ $company['name'] }} — Complete Payment History</p>
                @if($company['address'] ?? null)
                    <p class="text-2xs" style="color: #9ca3af; margin-top: 2px;">{{ $company['address'] }}</p>
                @endif
            </div>
        </td>
        <td class="text-right">
            <p class="text-sm" style="font-weight: 600;">
                Loan Ref: <span class="text-mono">{{ strtoupper(substr($loan->id, 0, 8)) }}</span>
            </p>
            <p class="text-2xs" style="text-transform: uppercase; letter-spacing: 1px; color: #9ca3af;">
                Generated: {{ now()->format('d M, Y') }}
            </p>
        </td>
    </tr>
</table>

<!-- Beneficiary & Loan Details -->
<table class="context-box">
    <tr>
        <td style="padding-right: 15px;">
            <p class="text-2xs" style="font-weight: bold; color: #9ca3af; text-transform: uppercase; margin-bottom: 4px;">Beneficiary</p>
            <p style="font-size: 16px; font-weight: bold; color: #111827;">{{ $loan->widow->full_name }}</p>
            <p class="text-sm text-mono" style="color: #4b5563;">Reg No: {{ $loan->widow->reg_no ?? 'N/A' }}</p>
            @if($loan->widow->deceased?->zone)
                <p class="text-2xs" style="color: #9ca3af; margin-top: 4px;">Zone: {{ $loan->widow->deceased->zone->name }}</p>
            @endif
        </td>
        <td style="padding-left: 15px;">
            <p class="text-2xs" style="font-weight: bold; color: #9ca3af; text-transform: uppercase; margin-bottom: 4px;">Loan Context</p>
            <p class="text-sm" style="font-weight: 500;">{{ $loan->purpose }}</p>
            <p class="text-sm" style="color: #6b7280;">Disbursed: {{ $loan->disbursed_at?->format('d M, Y') ?? 'N/A' }}</p>
            <p class="text-2xs" style="color: #9ca3af; margin-top: 4px;">
                Frequency: {{ $loan->repayment_frequency->getLabel() }}
            </p>
        </td>
    </tr>
</table>

<!-- Payment History Table -->
<table class="history-table">
    <thead>
    <tr>
        <th style="width: 15%;">Date Paid</th>
        <th style="width: 18%;">Receipt No.</th>
        <th style="width: 15%;">Method</th>
        <th class="text-right" style="width: 25%;">Amount Paid</th>
        <th class="text-right" style="width: 27%;">Balance After</th>
    </tr>
    </thead>
    <tbody>
    <?php
    $totalPayable = (float) ($loan->total_payable ?? $loan->principal_amount);
    $runningPaid = 0;
    $isAlternate = false;
    ?>

    @foreach($loan->repayments()->orderBy('paid_at')->orderBy('created_at')->get() as $payment)
            <?php
            $runningPaid += (float) $payment->amount;
            $balanceAfter = max(0, $totalPayable - $runningPaid);
            $isAlternate = !$isAlternate;
            ?>
        <tr class="{{ $isAlternate ? 'alternate-row' : '' }}">
            <td>{{ $payment->paid_at->format('d/m/Y') }}</td>
            <td class="text-mono" style="font-size: 10px;">
                @if($payment->receipt_number)
                    RCP-{{ str_pad($payment->receipt_number, 5, '0', STR_PAD_LEFT) }}
                @else
                    -
                @endif
            </td>
            <td>{{ ucfirst($payment->payment_method) }}</td>
            <!-- Used &#8358; entity and currency-cell class -->
            <td class="currency-cell" style="font-weight: 600;">NGN{{ number_format($payment->amount, 2) }}</td>
            <td class="currency-cell" style="color: #6b7280;">&#8358;{{ number_format($balanceAfter, 2) }}</td>
        </tr>
    @endforeach

    @if($loan->repayments()->count() === 0)
        <tr>
            <td colspan="5" style="text-align: center; padding: 20px; font-style: italic; color: #9ca3af;">
                No repayments have been recorded yet.
            </td>
        </tr>
    @endif
    </tbody>
</table>

<!-- Financial Summary -->
<div class="summary-box">
    <table class="summary-table">
        <tr>
            <td>Principal Amount:</td>
            <td class="currency-cell">&#8358;{{ number_format($loan->principal_amount, 2) }}</td>
        </tr>
        <tr>
            <td>Total Payable:</td>
            <td class="currency-cell">&#8358;{{ number_format($totalPayable, 2) }}</td>
        </tr>
        <tr class="highlight-row">
            <td>Total Paid to Date:</td>
            <td class="currency-cell">&#8358;{{ number_format($loan->total_paid, 2) }}</td>
        </tr>
        <tr class="total-row">
            <td>Outstanding Balance:</td>
            <td class="currency-cell {{ $loan->outstanding_balance > 0 ? 'text-red' : 'text-green' }}">
                &#8358;{{ number_format($loan->outstanding_balance, 2) }}
            </td>
        </tr>
    </table>
</div>

<!-- Float Clear -->
<div style="clear: both;"></div>

<!-- Footer -->
<table class="footer-table">
    <tr>
        <td style="width: 60%; vertical-align: bottom;">
            <p class="text-2xs" style="color: #9ca3af; font-style: italic; max-width: 300px;">
                This is a computer-generated statement reflecting all recorded repayments to date. If you notice any discrepancies, please contact the Finance Department immediately.
            </p>
        </td>
        <td class="text-right" style="width: 40%; vertical-align: bottom;">
            <div style="border-top: 1px solid #111827; width: 180px; margin-left: auto; padding-top: 5px;">
                <p class="text-2xs" style="font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #111827;">Authorized Signature</p>
                <p class="text-2xs" style="color: #9ca3af;">Finance &amp; Empowerment Dept.</p>
            </div>
        </td>
    </tr>
</table>

</body>
</html>
