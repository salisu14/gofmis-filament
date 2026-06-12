<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            color: #1f2937; /* gray-800 */
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

        .main-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        .main-table th { text-align: left; border-bottom: 2px solid #e5e7eb; padding: 8px 0; font-size: 10px; text-transform: uppercase; color: #4b5563; font-weight: bold; }
        .main-table td { padding: 12px 0; border-bottom: 1px solid #f3f4f6; vertical-align: top; }

        .summary-table { width: 50%; float: right; border-collapse: collapse; margin-bottom: 40px; }
        .summary-table td { padding: 4px 0; font-size: 12px; color: #6b7280; }
        .summary-table .total-row td { border-top: 1px solid #d1d5db; padding-top: 8px; font-size: 16px; font-weight: bold; color: #111827; }
        .text-red { color: #dc2626; }
        .text-green { color: #16a34a; }

        .footer-table { width: 100%; border-top: 2px dashed #e5e7eb; padding-top: 20px; }
        .badge { background-color: #f3f4f6; padding: 2px 8px; border-radius: 4px; font-size: 10px; color: #4b5563; border: 1px solid #d1d5db; }
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
                <h1 class="title">Weekly Repayment Receipt</h1>
                <p class="subtitle">{{ $company['name'] }} — Widow Support Program</p>
                @if($company['address'] ?? null)
                    <p class="text-2xs" style="color: #9ca3af; margin-top: 2px;">{{ $company['address'] }}</p>
                @endif
            </div>
        </td>
        <td class="text-right">
            <p class="text-sm" style="font-weight: 600;">
                @if($record->receipt_number)
                    Receipt No: <span class="text-mono">RCP-{{ str_pad($record->receipt_number, 5, '0', STR_PAD_LEFT) }}</span>
                @else
                    Ref: {{ $record->transaction?->reference ?? 'N/A' }}
                @endif
            </p>
            <p class="text-2xs" style="text-transform: uppercase; letter-spacing: 1px; color: #9ca3af;">
                Date: {{ $record->paid_at->format('d M, Y') }}
            </p>
        </td>
    </tr>
</table>

<!-- Beneficiary Details -->
<table class="context-box">
    <tr>
        <td style="padding-right: 15px;">
            <p class="text-2xs" style="font-weight: bold; color: #9ca3af; text-transform: uppercase; margin-bottom: 4px;">Beneficiary</p>
            <p style="font-size: 16px; font-weight: bold; color: #111827;">{{ $widow->full_name }}</p>
            <p class="text-sm text-mono" style="color: #4b5563;">Reg No: {{ $widow->reg_no ?? 'N/A' }}</p>
            @if($widow->deceased?->zone)
                <p class="text-2xs" style="color: #9ca3af; margin-top: 4px;">Zone: {{ $widow->deceased->zone->name }}</p>
            @endif
        </td>
        <td style="padding-left: 15px;">
            <p class="text-2xs" style="font-weight: bold; color: #9ca3af; text-transform: uppercase; margin-bottom: 4px;">Loan Context</p>
            <p class="text-sm" style="font-weight: 500;">{{ $record->widowLoan->purpose }}</p>
            <p class="text-sm" style="color: #6b7280; font-style: italic;">Status: {{ $record->widowLoan->status->getLabel() }}</p>
            <p class="text-2xs" style="color: #9ca3af; margin-top: 4px;">
                Total Loan Payable: ₦{{ number_format($record->widowLoan->total_payable ?? $record->widowLoan->principal_amount, 2) }}
            </p>
        </td>
    </tr>
</table>

<!-- Payment Details Table -->
<table class="main-table">
    <thead>
    <tr>
        <th>Description</th>
        <th>Method</th>
        <th class="text-right">Amount</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>
            <p style="font-weight: 600; color: #111827;">
                {{ $record->widowLoan->repayment_frequency->getLabel() }} Repayment
            </p>
            @if(isset($currentInstallment) && $totalInstallments > 0)
                <span class="text-2xs" style="color: #4b5563; font-weight: bold;">
                    Instalment {{ $currentInstallment }} of {{ $totalInstallments }}
                </span>
            @endif
            <br>
            @if($record->notes)
                <span class="text-2xs" style="color: #9ca3af; font-style: italic;">Note: {{ $record->notes }}</span>
            @endif
        </td>
        <td style="padding-top: 15px;">
            <span class="badge">{{ ucfirst($record->payment_method) }}</span>
        </td>
        <td class="text-right" style="font-weight: bold; font-size: 18px; color: #111827; padding-top: 15px;">
            ₦{{ number_format($record->amount, 2) }}
        </td>
    </tr>
    </tbody>
</table>

<!-- Financial Summary (Strictly the balance after this specific payment) -->
<table class="summary-table">
    <tr class="total-row">
        <td>Remaining Loan Balance:</td>
        <td class="text-right {{ $balance > 0 ? 'text-red' : 'text-green' }}">
            ₦{{ number_format($balance, 2) }}
        </td>
    </tr>
</table>

<!-- Float Clear -->
<div style="clear: both;"></div>

<!-- Footer / Signature -->
<table class="footer-table">
    <tr>
        <td style="width: 60%; vertical-align: bottom;">
            <p class="text-2xs" style="color: #9ca3af; font-style: italic; max-width: 250px;">
                This is a computer-generated receipt for the specific instalment shown above. For a complete history of all payments, please request a Loan Statement.
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
