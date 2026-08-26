<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle ?? 'Official Document' }}</title>
    <style>
        @page {
            margin: 12mm 15mm 15mm 15mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
            line-height: 1.4;
        }
        h1, h2, h3, h4 { margin: 0; }
        h1 { font-size: 18px; color: #111827; }
        h2 { font-size: 14px; margin-top: 4px; color: #374151; }
        h3 { font-size: 12px; margin-top: 6px; color: #4B5563; }
        
        .header {
            border-bottom: 2px solid #047857;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .brand-table { width: 100%; border-collapse: collapse; margin: 0; }
        .brand-table td { border: 0; padding: 0; vertical-align: middle; }
        .brand-logo { max-width: 65px; max-height: 65px; object-fit: contain; padding-right: 12px; }
        
        .doc-title-bar {
            background-color: #F3F4F6;
            border-left: 4px solid #047857;
            padding: 8px 12px;
            margin-bottom: 14px;
        }
        .doc-title-bar h2 {
            margin: 0;
            font-size: 14px;
            color: #065F46;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-title-bar .meta {
            font-size: 10px;
            color: #6B7280;
            margin-top: 2px;
        }
        
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 14px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #D1D5DB;
            padding: 6px 8px;
            vertical-align: top;
        }
        table.data-table th {
            background: #F9FAFB;
            text-align: left;
            font-weight: bold;
            color: #374151;
        }
        
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .info-grid td {
            border: 1px solid #E5E7EB;
            padding: 6px 8px;
            vertical-align: top;
        }
        .info-grid .label {
            background-color: #F9FAFB;
            font-weight: bold;
            color: #4B5563;
            width: 18%;
        }
        .info-grid .value {
            width: 32%;
            color: #111827;
        }
        
        .summary-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .summary-grid td {
            border: 1px solid #D1D5DB;
            padding: 8px 10px;
            text-align: center;
            background-color: #F9FAFB;
        }
        .summary-grid .num {
            font-size: 13px;
            font-weight: bold;
            color: #065F46;
            margin-top: 2px;
        }
        .summary-grid .lbl {
            font-size: 9px;
            color: #6B7280;
            text-transform: uppercase;
        }

        .muted { color: #6B7280; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        
        .signatures {
            width: 100%;
            margin-top: 35px;
            border-collapse: collapse;
        }
        .signatures td {
            border: 0;
            padding: 0 10px;
            vertical-align: top;
            width: 33%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #6B7280;
            margin-top: 45px;
            padding-top: 4px;
            font-size: 10px;
            font-weight: bold;
            color: #374151;
        }

        .footer {
            margin-top: 25px;
            font-size: 9px;
            color: #9CA3AF;
            text-align: center;
            border-top: 1px solid #E5E7EB;
            padding-top: 6px;
        }
    </style>
</head>
<body>
    @php
        $company = $companyContext ?? app(\App\Services\DocumentBrandingService::class)->getDocumentContext($documentTitle ?? null, $referenceNo ?? null);
    @endphp

    <div class="header">
        <table class="brand-table">
            <tr>
                @if($company['logo_data_uri'] ?? null)
                    <td style="width: 75px;">
                        <img src="{{ $company['logo_data_uri'] }}" class="brand-logo" alt="Logo">
                    </td>
                @endif
                <td>
                    <h1>{{ $company['organisation_name'] }}</h1>
                    @if(!empty($company['address']))
                        <div class="muted">{{ $company['address'] }}</div>
                    @endif
                    <div class="muted">
                        @if(!empty($company['phone'])) Tel: {{ $company['phone'] }} @endif
                        @if(!empty($company['email'])) | Email: {{ $company['email'] }} @endif
                        @if(!empty($company['website'])) | Web: {{ $company['website'] }} @endif
                        @if(!empty($company['registration_number'])) | Reg: {{ $company['registration_number'] }} @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    @if(isset($documentTitle))
        <div class="doc-title-bar">
            <h2>{{ $documentTitle }}</h2>
            @if(isset($subtitle) || isset($referenceNo))
                <div class="meta">
                    @if(isset($referenceNo)) <strong>Ref / No:</strong> {{ $referenceNo }} @endif
                    @if(isset($subtitle)) &nbsp;|&nbsp; {{ $subtitle }} @endif
                </div>
            @endif
        </div>
    @endif

    <div class="content">
        @yield('content')
    </div>

    <div class="footer">
        {{ $company['footer_text'] ?: ($company['organisation_name'] . ' — GOF MIS Official Document') }}
        &nbsp;|&nbsp; Generated on {{ now()->format('d M Y, h:i A') }} by {{ auth()->user()?->name ?? 'System' }}
    </div>
</body>
</html>
