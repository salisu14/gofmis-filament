<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WRL Weekly Repayment Report - {{ $repayment->receipt_number ?? $loan->id }}</title>
    <style>
        @page {
            margin: 2mm 3mm 2mm 3mm;
        }
        body {
            font-family: 'Courier', 'DejaVu Sans Mono', monospace;
            font-size: 8px;
            line-height: 1.15;
            color: #000000;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
            width: 100%;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }

        .header {
            text-align: center;
            margin-bottom: 4px;
        }
        .header-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .header-subtitle {
            font-size: 8px;
            margin-top: 1px;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 4px 0;
        }
        .double-divider {
            border-top: 2px solid #000;
            margin: 4px 0;
        }

        table.receipt-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.receipt-table td {
            padding: 1px 0;
            vertical-align: top;
        }
        .label {
            font-size: 7.5px;
            text-transform: uppercase;
        }
        .val {
            font-size: 8px;
        }
    </style>
</head>
<body>

@php
    $company = $company ?? app(\App\Services\Company\CompanyInformationService::class)->reportHeader();
    $widow = $loan->widow ?? $repayment->widowLoan?->widow;
    $deceased = $widow?->deceased;
    $zone = $deceased?->zone;
    $coordinator = $zone?->coordinator;
    
    $principalAmount = (float) $loan->principal_amount;
    $totalPayable = (float) ($loan->total_payable ?? $loan->principal_amount);
    $weeklyInstallment = (float) ($loan->weekly_installment ?? ($totalPayable > 0 ? $totalPayable / max(1, ($loan->duration_months ?? 6) * 4) : 0));
    
    $amountCollected = (float) ($repayment->amount ?? 0);
    $expectedCollection = $weeklyInstallment > 0 ? $weeklyInstallment : $amountCollected;
    $shortfall = max(0, $expectedCollection - $amountCollected);
    
    $paidAtDate = $repayment->paid_at ?? now();
    $totalPaidToDate = $repayment->total_paid_up_to_this ?? 0;
    $outstandingBalance = $repayment->balance_after ?? 0;
    
    $installmentCtx = $repayment->getInstallmentContext();
    $installmentN = $installmentCtx['n'];
    $installmentM = $installmentCtx['m'];
    
    $weekStart = $paidAtDate->copy()->startOfWeek()->format('d/m/Y');
    $weekEnd = $paidAtDate->copy()->endOfWeek()->format('d/m/Y');
    $receiptNo = $repayment->receipt_number ? 'RCP-'.str_pad($repayment->receipt_number, 5, '0', STR_PAD_LEFT) : ($repayment->transaction?->reference ?? 'N/A');
    $collectorName = $repayment->transaction?->creator?->name ?? auth()->user()?->name ?? 'System';
@endphp

<!-- Header -->
<div class="header">
    @if(!empty($company['logo_data_uri']))
        <div style="text-align: center; margin-bottom: 5px;">
            <img src="{{ $company['logo_data_uri'] }}" style="height: 30px; width: auto;" alt="">
        </div>
    @endif
    <div class="header-title">{{ mb_strtoupper($company['name']) }}</div>
    <div class="header-subtitle">WRL REPAYMENT RECEIPT</div>
    <div style="font-size: 7.5px; margin-top: 2px;">
        @if(!empty($company['address'])) {{ $company['address'] }} <br> @endif
        @if(!empty($company['phone'])) Tel: {{ $company['phone'] }} @endif
        @if(!empty($company['email'])) <br> Email: {{ $company['email'] }} @endif
    </div>
    <div style="font-size: 7.5px; margin-top: 2px;">Thermal Output (58mm)</div>
</div>

<div class="divider"></div>

<!-- Reporting Period -->
<table class="receipt-table">
    <tr>
        <td class="label">Installment Week:</td>
        <td class="val text-right bold">Week {{ $installmentN }} of {{ $installmentM }}</td>
    </tr>
    <tr>
        <td class="label">Week Period:</td>
        <td class="val text-right">{{ $weekStart }} - {{ $weekEnd }}</td>
    </tr>
    <tr>
        <td class="label">Date Paid:</td>
        <td class="val text-right">{{ $paidAtDate->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="label">Receipt Ref:</td>
        <td class="val text-right bold">{{ $receiptNo }}</td>
    </tr>
</table>

<div class="divider"></div>

<!-- Widow / Loan Context -->
<table class="receipt-table">
    <tr>
        <td class="label">Widow Name:</td>
        <td class="val text-right bold">{{ $widow->full_name ?? 'N/A' }}</td>
    </tr>
    <tr>
        <td class="label">Widow Reg #:</td>
        <td class="val text-right">{{ $widow->reg_no ?? 'N/A' }}</td>
    </tr>
    <tr>
        <td class="label">Loan Ref #:</td>
        <td class="val text-right">{{ $loan->reference_number ?? substr($loan->id, 0, 8) }}</td>
    </tr>
    <tr>
        <td class="label">Zone:</td>
        <td class="val text-right">{{ $zone->name ?? 'N/A' }}</td>
    </tr>
    <tr>
        <td class="label">Coordinator:</td>
        <td class="val text-right">{{ $coordinator->name ?? $zone->coordinator_name ?? 'N/A' }}</td>
    </tr>
</table>

<div class="divider"></div>

<!-- Financial Calculations -->
<table class="receipt-table">
    <tr>
        <td class="label">Principal Amount:</td>
        <td class="val text-right">₦{{ number_format($principalAmount, 2) }}</td>
    </tr>
    <tr>
        <td class="label">Total Payable:</td>
        <td class="val text-right">₦{{ number_format($totalPayable, 2) }}</td>
    </tr>
    <tr>
        <td class="label">Weekly Installment:</td>
        <td class="val text-right">₦{{ number_format($weeklyInstallment, 2) }}</td>
    </tr>
    <tr>
        <td class="label">Expected Collection:</td>
        <td class="val text-right">₦{{ number_format($expectedCollection, 2) }}</td>
    </tr>
    <tr class="bold">
        <td class="label bold">Amount Collected:</td>
        <td class="val text-right bold">₦{{ number_format($amountCollected, 2) }}</td>
    </tr>
    <tr>
        <td class="label">Arrears / Shortfall:</td>
        <td class="val text-right">₦{{ number_format($shortfall, 2) }}</td>
    </tr>
</table>

<div class="divider"></div>

<!-- Running Totals -->
<table class="receipt-table">
    <tr>
        <td class="label">Total Paid to Date:</td>
        <td class="val text-right">₦{{ number_format($totalPaidToDate, 2) }}</td>
    </tr>
    <tr class="bold">
        <td class="label bold">Outstanding Balance:</td>
        <td class="val text-right bold">₦{{ number_format($outstandingBalance, 2) }}</td>
    </tr>
    <tr>
        <td class="label">Payment Method:</td>
        <td class="val text-right">{{ ucfirst($repayment->payment_method ?? 'Cash') }}</td>
    </tr>
    <tr>
        <td class="label">Collector / User:</td>
        <td class="val text-right">{{ $collectorName }}</td>
    </tr>
    <tr>
        <td class="label">Payment Status:</td>
        <td class="val text-right bold">POSTED</td>
    </tr>
</table>

<div class="double-divider"></div>

<!-- Summary Box -->
<div class="text-center bold" style="font-size: 8.5px; margin: 3px 0;">
    REPAYMENT SUMMARY<br>
    Collected: ₦{{ number_format($amountCollected, 2) }} | Shortfall: ₦{{ number_format($shortfall, 2) }}<br>
    Balance: ₦{{ number_format($outstandingBalance, 2) }}
</div>

<div class="divider"></div>

<!-- Footer -->
<div class="text-center" style="font-size: 7px; color: #333;">
    Printed: {{ now()->format('d/m/Y H:i:s') }}<br>
    {{ $company['name'] }} - WRL Program
</div>

</body>
</html>
