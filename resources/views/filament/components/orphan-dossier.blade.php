<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orphan Dossier Report - {{ $orphan->reg_no ?? $orphan->id }}</title>
    <style>
        @page {
            margin: 12mm 15mm 15mm 15mm;
        }
        body {
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #374151;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .brand-logo {
            width: 48px;
            max-height: 48px;
            object-fit: contain;
            vertical-align: middle;
            margin-right: 10px;
        }
        .brand-title {
            font-size: 18px;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .brand-subtitle {
            font-size: 10px;
            color: #4b5563;
            margin-top: 2px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .text-mono {
            font-family: 'Courier New', monospace;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #e5e7eb;
            color: #374151;
        }
        .badge-success { background-color: #dcfce7; color: #166534; }
        .badge-warning { background-color: #fef9c3; color: #854d0e; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        .badge-info { background-color: #e0f2fe; color: #075985; }

        .profile-container {
            width: 100%;
            margin-bottom: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            background-color: #f9fafb;
            padding: 10px;
        }
        .photo-td {
            width: 110px;
            vertical-align: top;
            padding-right: 15px;
        }
        .orphan-photo {
            width: 100px;
            height: 120px;
            object-fit: cover;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background-color: #f3f4f6;
        }
        .no-photo-box {
            width: 100px;
            height: 120px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background-color: #e5e7eb;
            color: #6b7280;
            text-align: center;
            line-height: 120px;
            font-size: 10px;
        }
        .details-td {
            vertical-align: top;
        }

        .section-heading {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            color: #111827;
            border-bottom: 1px solid #9ca3af;
            padding-bottom: 3px;
            margin-top: 15px;
            margin-bottom: 8px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .info-table td {
            padding: 3px 6px;
            vertical-align: top;
        }
        .info-label {
            font-size: 9px;
            font-weight: bold;
            color: #6b7280;
            text-transform: uppercase;
            width: 30%;
        }
        .info-value {
            font-size: 11px;
            color: #111827;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .data-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: left;
            padding: 5px 6px;
            border: 1px solid #e5e7eb;
        }
        .data-table td {
            padding: 5px 6px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
            vertical-align: top;
        }
        .empty-text {
            font-size: 10px;
            color: #6b7280;
            font-style: italic;
            padding: 6px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid #e5e7eb;
            padding-top: 5px;
            font-size: 8px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>

@php
    $company = $company ?? app(\App\Services\Company\CompanyInformationService::class)->reportHeader();
    $fmtEnum = function ($val) {
        if (blank($val)) return 'N/A';
        if (! is_object($val)) return ucfirst((string) $val);
        if (method_exists($val, 'label')) return $val->label();
        if (method_exists($val, 'getLabel')) return $val->getLabel();
        return ucfirst((string) ($val->value ?? ''));
    };
    $fullName = $orphan->display_name ?? $orphan->full_name ?? trim($orphan->first_name . ' ' . ($orphan->middle_name ? $orphan->middle_name . ' ' : '') . $orphan->last_name);
    if (blank($fullName)) {
        $fullName = 'Orphan #' . substr($orphan->id, 0, 8);
    }
    $statusLabel = $fmtEnum($orphan->status);
    $genderLabel = $fmtEnum($orphan->gender);
    $deceasedName = $deceased?->display_name ?? $deceased?->full_name ?? trim(($deceased->first_name ?? '') . ' ' . ($deceased->last_name ?? ''));
@endphp

<!-- Header -->
<table class="header-table">
    <tr>
        <td>
            @if(!empty($company['logo_data_uri']))
                <img src="{{ $company['logo_data_uri'] }}" class="brand-logo" alt="">
            @endif
            <div style="display: inline-block; vertical-align: middle;">
                <h1 class="brand-title">Comprehensive Orphan Dossier</h1>
                <p class="brand-subtitle">{{ $company['name'] }} — Beneficiary Case Profile</p>
                <div style="font-size: 10px; color: #4b5563; margin-top: 2px;">
                    @if(!empty($company['address'])) {{ $company['address'] }} <br> @endif
                    @if(!empty($company['phone'])) Tel: {{ $company['phone'] }} @endif
                    @if(!empty($company['email'])) | Email: {{ $company['email'] }} @endif
                </div>
            </div>
        </td>
        <td class="text-right">
            <p style="font-size: 10px; font-weight: bold; margin: 0;">Ref: <span class="text-mono">{{ $orphan->reg_no ?? 'UNREGISTERED' }}</span></p>
            <p style="font-size: 9px; color: #6b7280; margin-top: 2px;">Generated: {{ $generated_at->format('d M, Y H:i') }}</p>
        </td>
    </tr>
</table>

<!-- Profile Summary Container -->
<table class="profile-container">
    <tr>
        <td class="photo-td">
            @if(!empty($photo_data_uri))
                <img src="{{ $photo_data_uri }}" class="orphan-photo" alt="Orphan Photo">
            @else
                <div class="no-photo-box">NO PHOTO</div>
            @endif
        </td>
        <td class="details-td">
            <h2 style="font-size: 16px; margin: 0 0 6px 0; color: #111827;">{{ $fullName }}</h2>
            <table class="info-table" style="margin-bottom: 0;">
                <tr>
                    <td class="info-label">Reg. Number:</td>
                    <td class="info-value text-mono"><strong>{{ $orphan->reg_no ?? 'N/A' }}</strong></td>
                    <td class="info-label">Gender:</td>
                    <td class="info-value">{{ $genderLabel }}</td>
                </tr>
                <tr>
                    <td class="info-label">NIN:</td>
                    <td class="info-value text-mono">{{ $orphan->nin ?? 'N/A' }}</td>
                    <td class="info-label">Date of Birth:</td>
                    <td class="info-value">{{ $orphan->birth_date ? $orphan->birth_date->format('d M, Y') : 'N/A' }} (Age: {{ $orphan->age ?? ($orphan->birth_date ? $orphan->birth_date->age : 'N/A') }})</td>
                </tr>
                <tr>
                    <td class="info-label">Eligibility Status:</td>
                    <td class="info-value">
                        @if($orphan->is_eligible)
                            <span class="badge badge-success">Eligible</span>
                        @else
                            <span class="badge badge-danger">Ineligible</span>
                        @endif
                    </td>
                    <td class="info-label">Current Status:</td>
                    <td class="info-value">
                        <span class="badge badge-info">{{ $statusLabel }}</span>
                        @if($orphan->is_married)
                            <span class="badge badge-warning">Married</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="info-label">Vulnerability:</td>
                    <td class="info-value">{{ $orphan->vulnerability_status?->getLabel() ?? $deceased?->vulnerability_status?->getLabel() ?? 'Standard' }}</td>
                    <td class="info-label">Sponsorship:</td>
                    <td class="info-value">
                        @php
                            $activeSponsorships = $orphan->sponsorships->filter(function ($s) {
                                $started = blank($s->start_date) || $s->start_date->lte(today());
                                $notEnded = blank($s->end_date) || $s->end_date->gte(today());

                                return $started && $notEnded;
                            });
                        @endphp
                        @if($activeSponsorships->count() > 0)
                            <span class="badge badge-success">Sponsored</span>
                        @elseif($orphan->sponsorships->count() > 0)
                            <span class="badge badge-warning">Sponsorship on Record</span>
                        @else
                            <span class="badge">Not Sponsored</span>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<!-- Section: Deceased Parent & Household Information -->
<div class="section-heading">1. Deceased Parent & Household Information</div>
<table class="info-table" style="background-color: #fff; border: 1px solid #e5e7eb; border-radius: 4px; padding: 6px;">
    <tr>
        <td class="info-label">Deceased Parent:</td>
        <td class="info-value"><strong>{{ $deceasedName ?: 'N/A' }}</strong> (Reg: <span class="text-mono">{{ $deceased->reg_no ?? 'N/A' }}</span>)</td>
        <td class="info-label">Date of Death:</td>
        <td class="info-value">{{ $deceased?->date_of_death ? $deceased->date_of_death->format('d M, Y') : 'N/A' }}</td>
    </tr>
    <tr>
        <td class="info-label">Assigned Zone:</td>
        <td class="info-value">{{ $deceased?->zone?->name ?? 'N/A' }}</td>
        <td class="info-label">Zone Coordinator:</td>
        <td class="info-value">{{ $deceased?->zone?->coordinator?->name ?? $deceased?->zone?->coordinator_name ?? 'N/A' }} {{ $deceased?->zone?->coordinator_phone ? '('.$deceased->zone->coordinator_phone.')' : '' }}</td>
    </tr>
    <tr>
        <td class="info-label">Guardian / Widow:</td>
        <td class="info-value">
            @php
                $guardianWidow = $deceased?->widows?->first();
            @endphp
            {{ $deceased?->guardian_name ?? ($guardianWidow?->full_name ?? 'N/A') }}
        </td>
        <td class="info-label">Guardian Contact:</td>
        <td class="info-value">{{ $deceased?->guardian_phone ?? $orphan->address ?? 'N/A' }}</td>
    </tr>
</table>

<!-- Section: Education History -->
<div class="section-heading">2. Education Record & Support History</div>
@if($orphan->educations->count() > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>Institution / School</th>
                <th>Class / Level</th>
                <th>Fee Frequency</th>
                <th class="text-right">School Fee</th>
                <th class="text-right">Support Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orphan->educations as $edu)
                <tr>
                    <td><strong>{{ $edu->institution?->name ?? 'N/A' }}</strong></td>
                    <td>{{ $edu->orphanClass?->name ?? $edu->class_level ?? 'N/A' }}</td>
                    <td>{{ ucfirst($edu->fee_frequency ?? 'N/A') }}</td>
                    <td class="text-right">₦{{ number_format((float) ($edu->school_fee ?? 0), 2) }}</td>
                    <td class="text-right">₦{{ number_format((float) ($edu->support_amount ?? 0), 2) }}</td>
                    <td>
                        @if($edu->is_current)
                            <span class="badge badge-success">Active / Current</span>
                        @else
                            <span class="badge">Past</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="empty-text">No education or school support records registered for this orphan.</p>
@endif

<!-- Section: Healthcare & Prescription History -->
<div class="section-heading">3. Healthcare & Medical Records</div>
@if($orphan->prescriptions->count() > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Illness / Diagnosis</th>
                <th>Medications / Drugs</th>
                <th class="text-right">Lab Cost</th>
                <th class="text-right">Drug Cost</th>
                <th class="text-right">Total Cost</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orphan->prescriptions as $p)
                @php
                    $medList = $p->medications->pluck('name')->implode(', ');
                    $labCost = (float) ($p->lab_test_cost ?? 0);
                    $drugCost = (float) ($p->drug_cost ?? 0);
                    $totalCost = $labCost + $drugCost;
                    $pStatus = $fmtEnum($p->status);
                @endphp
                <tr>
                    <td>{{ $p->prescription_date ? $p->prescription_date->format('d/m/Y') : $p->created_at->format('d/m/Y') }}</td>
                    <td><strong>{{ $p->illnessModel?->name ?? $p->illness ?? 'N/A' }}</strong></td>
                    <td>{{ $medList ?: 'N/A' }}</td>
                    <td class="text-right">₦{{ number_format($labCost, 2) }}</td>
                    <td class="text-right">₦{{ number_format($drugCost, 2) }}</td>
                    <td class="text-right"><strong>₦{{ number_format($totalCost, 2) }}</strong></td>
                    <td><span class="badge badge-info">{{ $pStatus }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="empty-text">No medical treatment or prescription records registered for this orphan.</p>
@endif

<!-- Section: Intervention History -->
<div class="section-heading">4. Special Interventions & Support Requests</div>
@php
    $interventionsList = $orphan->interventionRequests;
    if ($interventionsList->count() === 0) {
        $interventionsList = $orphan->interventions;
    }
@endphp
@if($interventionsList->count() > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Intervention Type</th>
                <th>Requested Details / Amount</th>
                <th>Approved Details / Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($interventionsList as $item)
                @php
                    $iType = $item->type?->name ?? 'Special Support';
                    $iDate = $item->created_at ? $item->created_at->format('d/m/Y') : 'N/A';
                    // InterventionRequest carries requested_amount + notes;
                    // an Intervention (fulfilment) carries a posted amount.
                    $isFulfilment = $item instanceof \App\Models\Intervention;
                    $reqVal = $item->requested_amount !== null
                        ? '₦'.number_format((float) $item->requested_amount, 2)
                        : ($item->notes ?? 'N/A');
                    $appVal = $item->amount !== null
                        ? '₦'.number_format((float) $item->amount, 2)
                        : ($isFulfilment ? $reqVal : 'N/A');
                    $iStatus = $fmtEnum($item->status ?? '');
                @endphp
                <tr>
                    <td>{{ $iDate }}</td>
                    <td><strong>{{ $iType }}</strong></td>
                    <td>{{ $reqVal }}@if($item->notes ?? null)<br><span style="font-size:9px;color:#4b5563;">{{ $item->notes }}</span>@endif</td>
                    <td>{{ $appVal }}</td>
                    <td><span class="badge badge-info">{{ $iStatus }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="empty-text">No individual intervention or special support requests recorded.</p>
@endif

<!-- Section: Household Welfare Support History -->
<div class="section-heading">5. Household Welfare Package History</div>
@if($welfare->count() > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>Package Name</th>
                <th>Nomination Status</th>
                <th>Collection Status</th>
                <th>Collection Date</th>
                <th>Package Items</th>
            </tr>
        </thead>
        <tbody>
            @foreach($welfare as $w)
                @php
                    $pkgName = $w->welfarePackage?->name ?? 'Welfare Package';
                    $wStatus = $fmtEnum($w->status);
                    $itemsSummary = $w->welfarePackage?->items
                        ? $w->welfarePackage->items->map(fn($item) => ($item->item?->name ?? 'Item') . ' (' . $item->quantity_per_family . ')')->implode(', ')
                        : 'N/A';
                @endphp
                <tr>
                    <td><strong>{{ $pkgName }}</strong></td>
                    <td><span class="badge badge-info">{{ $wStatus }}</span></td>
                    <td>
                        @if($w->isCollected())
                            <span class="badge badge-success">Collected</span>
                        @elseif($w->isApproved() && ! $w->isCollected())
                            <span class="badge badge-warning">Approved / Not Collected</span>
                        @else
                            <span class="badge badge-warning">Pending</span>
                        @endif
                    </td>
                    <td>{{ $w->collected_at ? \Carbon\Carbon::parse($w->collected_at)->format('d/m/Y') : 'N/A' }}</td>
                    <td>{{ $itemsSummary }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="empty-text">No household welfare package distributions recorded.</p>
@endif

<!-- Section: Sponsorship Details -->
<div class="section-heading">6. Sponsorship Information</div>
@if($orphan->sponsorships->count() > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>Sponsor Name</th>
                <th>Support Amount / Type</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orphan->sponsorships as $sp)
                @php
                    $spStarted = blank($sp->start_date) || $sp->start_date->lte(today());
                    $spNotEnded = blank($sp->end_date) || $sp->end_date->gte(today());
                    $spStatus = ($spStarted && $spNotEnded) ? 'Active' : 'Past';
                @endphp
                <tr>
                    <td><strong>{{ $sp->sponsor?->name ?? ($sp->sponsor_name ?? 'Anonymous Sponsor') }}</strong></td>
                    <td>₦{{ number_format((float) ($sp->amount_committed ?? 0), 2) }}</td>
                    <td>{{ $sp->start_date ? \Carbon\Carbon::parse($sp->start_date)->format('d/m/Y') : 'N/A' }}</td>
                    <td>{{ $sp->end_date ? \Carbon\Carbon::parse($sp->end_date)->format('d/m/Y') : 'Ongoing' }}</td>
                    <td><span class="badge {{ $spStatus === 'Active' ? 'badge-success' : 'badge' }}">{{ $spStatus }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="empty-text">No active or historical sponsorship records on file.</p>
@endif

<!-- Footer -->
<div class="footer">
    <p>{{ $company['name'] }} — Official Beneficiary Dossier — Confidential Document — Page 1 of 1</p>
</div>

</body>
</html>
