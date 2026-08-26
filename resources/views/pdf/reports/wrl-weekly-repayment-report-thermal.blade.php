<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WRL Weekly Repayment Report - {{ $weekStart->format('Y-m-d') }}</title>
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

        .header { text-align: center; margin-bottom: 4px; }
        .header-title { font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .header-subtitle { font-size: 8px; margin-top: 1px; }

        .divider { border-top: 1px dashed #000; margin: 4px 0; }
        .double-divider { border-top: 2px solid #000; margin: 4px 0; }

        /* Single unified table keeps columns from overflowing 58mm width. */
        table.report {
            width: 100%;
            border-collapse: collapse;
        }
        table.report th {
            font-size: 7px;
            text-transform: uppercase;
            text-align: left;
            border-bottom: 1px solid #000;
            padding: 2px 1px;
        }
        table.report td {
            font-size: 7.5px;
            padding: 1px 1px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .total-row td {
            border-top: 1px solid #000;
            font-weight: bold;
        }
        .label { font-size: 7.5px; text-transform: uppercase; }
        .val { font-size: 8px; }
    </style>
</head>
<body>

@php
    $company = $company ?? app(\App\Services\Company\CompanyInformationService::class)->reportHeader();
@endphp

<!-- Header -->
<div class="header">
    @if(!empty($company['logo_data_uri']))
        <div style="text-align: center; margin-bottom: 4px;">
            <img src="{{ $company['logo_data_uri'] }}" style="height: 26px; width: auto;" alt="">
        </div>
    @endif
    <div class="header-title">{{ mb_strtoupper($company['name']) }}</div>
    <div class="header-subtitle">WRL WEEKLY REPAYMENT REPORT</div>
    <div style="font-size: 7.5px; margin-top: 1px;">Thermal Output (58mm)</div>
</div>

<div class="divider"></div>

<!-- Reporting Week -->
<table class="report" style="margin-bottom: 4px;">
    <tr>
        <td class="label">Week Period:</td>
        <td class="val text-right bold">{{ $weekStart->format('d/m/Y') }} - {{ $weekEnd->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="label">Zone:</td>
        <td class="val text-right">{{ $zone ?: 'ALL ZONES' }}</td>
    </tr>
    <tr>
        <td class="label">Week Start:</td>
        <td class="val text-right">{{ $weekStart->format('D, d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="label">Week End:</td>
        <td class="val text-right">{{ $weekEnd->format('D, d/m/Y') }}</td>
    </tr>
</table>

<div class="divider"></div>

<!-- Schedule-driven weekly rows (loan with a due instalment this week) -->
<table class="report">
    <thead>
        <tr>
            <th style="width: 14%;">Due</th>
            <th style="width: 28%;">Widow</th>
            <th style="width: 18%;">Loan</th>
            <th style="width: 13%; text-align: right;">Due&#8358;</th>
            <th style="width: 13%; text-align: right;">Paid</th>
            <th style="width: 14%; text-align: right;">Shf</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
            @php($widow = $row['widow'])
            <tr>
                <td>{{ $row['due_date'] ? $row['due_date']->format('d/m') : 'N/A' }}</td>
                <td class="bold">{{ Str::limit($widow?->full_name ?? 'N/A', 18) }}</td>
                <td>{{ $row['loan_reference'] }}</td>
                <td class="text-right">{{ number_format($row['expected'], 0) }}</td>
                <td class="text-right bold">{{ number_format($row['actual'], 0) }}</td>
                <td class="text-right">{{ number_format($row['shortfall'], 0) }}</td>
            </tr>
            <tr>
                <td colspan="6" style="font-size: 6.6px; color: #333;">
                    &nbsp;{{ $row['zone_name'] }}
                    @if(! $row['collected'])
                        &nbsp;&middot;&nbsp;NO COLLECTION
                    @endif
                    @if($row['shortfall'] > 0)
                        &nbsp;&middot;&nbsp;Shortfall ₦{{ number_format($row['shortfall'], 2) }}
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="text-center" style="padding: 6px 0;">No WRL instalments due for this week.</td>
            </tr>
        @endforelse
        <tr class="total-row">
            <td colspan="3">Totals ({{ $scheduleCount }} due / {{ $distinctLoans }} loans)</td>
            <td class="text-right">{{ number_format($expectedTotal, 0) }}</td>
            <td class="text-right">{{ number_format($collectedTotal, 0) }}</td>
            <td class="text-right">{{ number_format($shortfallTotal, 0) }}</td>
        </tr>
    </tbody>
</table>

<div class="double-divider"></div>

<!-- Week Summary -->
<table class="report">
    <tr>
        <td class="label">Expected Collection (week):</td>
        <td class="val text-right">₦{{ number_format($expectedTotal, 2) }}</td>
    </tr>
    <tr class="total-row">
        <td class="label bold">Amount Collected (week):</td>
        <td class="val text-right bold">₦{{ number_format($collectedTotal, 2) }}</td>
    </tr>
    <tr>
        <td class="label">Shortfall / Arrears (week):</td>
        <td class="val text-right">₦{{ number_format($shortfallTotal, 2) }}</td>
    </tr>
    <tr>
        <td class="label">Instalments Due / Loans:</td>
        <td class="val text-right">{{ $scheduleCount }} / {{ $distinctLoans }}</td>
    </tr>
    <tr>
        <td class="label">Repayments Collected:</td>
        <td class="val text-right">{{ $repaymentCount }}</td>
    </tr>
    <tr>
        <td class="label">Remaining Balance (active-week loans):</td>
        <td class="val text-right">₦{{ number_format($remainingBalanceTotal, 2) }}</td>
    </tr>
</table>

<div class="divider"></div>

<div class="text-center" style="font-size: 7px; color: #333;">
    Printed: {{ $generatedAt->format('d/m/Y H:i:s') }}<br>
    {{ $company['name'] }} - WRL Program
</div>

</body>
</html>