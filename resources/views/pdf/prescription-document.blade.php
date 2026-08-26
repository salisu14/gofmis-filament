@extends('pdf.layouts.official-document', [
    'documentTitle' => 'PRESCRIPTION / MEDICAL TREATMENT FORM',
    'referenceNo' => 'RX-' . strtoupper(substr($prescription->id, 0, 8)),
    'subtitle' => 'Prescription Date: ' . ($prescription->prescription_date?->format('d M Y') ?? 'N/A')
])

@section('content')
    @php
        $patient = $prescription->prescribable;
        $isWidow = $prescription->prescribable_type === \App\Models\Widow::class;
        $patientCategory = $isWidow ? 'Widow' : 'Orphan';
        $deceased = $patient?->deceased;
        $zoneName = $deceased?->zone?->name ?? 'N/A';
    @endphp

    <h3>BENEFICIARY / PATIENT INFORMATION</h3>
    <table class="info-grid">
        <tr>
            <td class="label">Patient Name:</td>
            <td class="value"><strong>{{ $patient?->full_name ?? trim(($patient?->first_name ?? '') . ' ' . ($patient?->last_name ?? '')) ?: 'N/A' }}</strong></td>
            <td class="label">Category:</td>
            <td class="value">{{ $patientCategory }}</td>
        </tr>
        <tr>
            <td class="label">Registration No:</td>
            <td class="value">{{ $patient?->reg_no ?? 'N/A' }}</td>
            <td class="label">NIN:</td>
            <td class="value">{{ $patient?->nin ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Zone:</td>
            <td class="value">{{ $zoneName }}</td>
            <td class="label">Gender:</td>
            <td class="value">{{ $patient?->gender?->getLabel() ?? $patient?->gender ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Deceased / Guardian:</td>
            <td class="value" colspan="3">{{ $deceased ? ($deceased->first_name . ' ' . $deceased->last_name . ' (' . $deceased->reg_no . ')') : 'N/A' }}</td>
        </tr>
    </table>

    <h3>CLINICAL & DIAGNOSIS DETAILS</h3>
    <table class="info-grid">
        <tr>
            <td class="label">Prescription Date:</td>
            <td class="value">{{ $prescription->prescription_date?->format('d M Y') ?? 'N/A' }}</td>
            <td class="label">Doctor / Hospital:</td>
            <td class="value">{{ $prescription->doctor_name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Diagnosis / Illness:</td>
            <td class="value"><strong>{{ $prescription->illness_name ?? 'N/A' }}</strong></td>
            <td class="label">Status:</td>
            <td class="value">
                <span class="bold" style="color: {{ $prescription->isTreated() ? '#047857' : '#D97706' }}">
                    {{ $prescription->status?->getLabel() ?? ucfirst((string)$prescription->status) }}
                </span>
            </td>
        </tr>
        <tr>
            <td class="label">Issuing Staff:</td>
            <td class="value">{{ $prescription->user?->name ?? 'N/A' }}</td>
            <td class="label">Clinical Notes:</td>
            <td class="value">{{ $prescription->note ?? 'None' }}</td>
        </tr>
    </table>

    <h3>PRESCRIBED MEDICATIONS</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Medication Name</th>
                <th style="width: 30%;">Dosage / Instructions</th>
                <th style="width: 20%;" class="right">Type</th>
            </tr>
        </thead>
        <tbody>
            @forelse($prescription->medications as $index => $medication)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td><strong>{{ $medication->name }}</strong>@if($medication->description)<br><span class="muted">{{ $medication->description }}</span>@endif</td>
                    <td>{{ $medication->pivot?->dosage ?? 'As directed' }}</td>
                    <td class="right">{{ $medication->type ?? 'General' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="center muted">No specific medications itemized on this prescription record.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h3>FINANCIAL BREAKDOWN</h3>
    <table class="info-grid">
        <tr>
            <td class="label">Lab Test Cost:</td>
            <td class="value">NGN {{ number_format((float) $prescription->lab_test_cost, 2) }}</td>
            <td class="label">Drug Cost:</td>
            <td class="value">NGN {{ number_format((float) $prescription->drug_cost, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Total Cost:</td>
            <td class="value" colspan="3"><strong style="font-size: 13px; color: #065F46;">NGN {{ number_format((float) $prescription->total_cost, 2) }}</strong></td>
        </tr>
    </table>

    @if($prescription->isTreated())
        <h3>TREATMENT COMPLETION DETAILS</h3>
        <table class="info-grid">
            <tr>
                <td class="label">Completed Date:</td>
                <td class="value">{{ $prescription->treated_at?->format('d M Y, h:i A') ?? 'N/A' }}</td>
                <td class="label">Completed By:</td>
                <td class="value">{{ $prescription->treatedBy?->name ?? 'Authorized Staff' }}</td>
            </tr>
            @if($prescription->treatment_notes)
                <tr>
                    <td class="label">Treatment Notes:</td>
                    <td class="value" colspan="3">{{ $prescription->treatment_notes }}</td>
                </tr>
            @endif
        </table>
    @endif

    <table class="signatures">
        <tr>
            <td>
                <div class="signature-line">
                    Doctor / Medical Officer Signature<br>
                    <span class="muted font-weight-normal">{{ $prescription->doctor_name ?? 'Attending Doctor' }}</span>
                </div>
            </td>
            <td>
                <div class="signature-line">
                    Foundation Authorized Officer<br>
                    <span class="muted font-weight-normal">{{ $prescription->user?->name ?? 'Authorized Staff' }}</span>
                </div>
            </td>
            <td>
                <div class="signature-line">
                    Beneficiary / Guardian Signature<br>
                    <span class="muted font-weight-normal">{{ $patient?->full_name ?? 'Beneficiary' }}</span>
                </div>
            </td>
        </tr>
    </table>
@endsection
