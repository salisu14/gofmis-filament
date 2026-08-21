@extends('pdf.layouts.official-document', [
    'documentTitle' => 'MEDICAL REFERRAL / CONSULTATION FORM',
])

@section('document-content')
@php
    $patient = $prescription->prescribable;
    $deceased = $patient?->deceased;
    $zone = $deceased?->zone;
    $isOrphan = $prescription->prescribable_type === \App\Models\Orphan::class;
    $categoryLabel = $isOrphan ? 'Orphan' : 'Widow';

    $age = 'N/A';
    if ($patient?->birth_date) {
        $age = \Carbon\Carbon::parse($patient->birth_date)->age . ' years';
    }

    $referralNo = 'REF-' . strtoupper(substr(str_replace('-', '', $prescription->id), 0, 8));
    $referralDate = $prescription->prescription_date ? $prescription->prescription_date->format('d M Y') : now()->format('d M Y');
@endphp

<div style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px 14px; margin-bottom: 15px; background-color: #f8fafc;">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 50%;">
                <span style="font-size: 8.5pt; color: #64748b; font-weight: bold; text-transform: uppercase;">Referral Number:</span>
                <div style="font-size: 11pt; font-weight: bold; color: #0f172a;">{{ $referralNo }}</div>
            </td>
            <td style="width: 50%; text-align: right;">
                <span style="font-size: 8.5pt; color: #64748b; font-weight: bold; text-transform: uppercase;">Referral Date:</span>
                <div style="font-size: 11pt; font-weight: bold; color: #0f172a;">{{ $referralDate }}</div>
            </td>
        </tr>
    </table>
</div>

<div class="section-title">PATIENT INFORMATION</div>
<table class="info-table" style="margin-bottom: 15px;">
    <tr>
        <th>Full Name:</th>
        <td>{{ $patient?->full_name ?? 'N/A' }}</td>
        <th>Registration No:</th>
        <td>{{ $patient?->reg_no ?? 'N/A' }}</td>
    </tr>
    <tr>
        <th>Category:</th>
        <td><span class="badge" style="background-color: #e2e8f0; color: #1e293b;">{{ $categoryLabel }}</span></td>
        <th>Gender / Age:</th>
        <td>{{ $patient?->gender?->getLabel() ?? ($isOrphan ? 'N/A' : 'Female') }} / {{ $age }}</td>
    </tr>
    <tr>
        <th>Assigned Zone:</th>
        <td>{{ $zone?->name ?? 'N/A' }}</td>
        <th>Deceased Family:</th>
        <td>{{ $deceased?->full_name ?? 'N/A' }}</td>
    </tr>
    <tr>
        <th>Guardian Contact:</th>
        <td>{{ $patient?->phone ?? $deceased?->guardian_phone ?? 'N/A' }}</td>
        <th>NIN / ID:</th>
        <td>{{ $patient?->nin ?? 'N/A' }}</td>
    </tr>
</table>

<div class="section-title">REFERRAL INFORMATION</div>
<table class="info-table" style="margin-bottom: 15px;">
    <tr>
        <th>Referred Hospital / Clinic:</th>
        <td>{{ $prescription->hospital_name ?? 'Specialist Hospital / Primary Healthcare Center' }}</td>
        <th>Attending Doctor (Optional):</th>
        <td>{{ $prescription->doctor_name ?? 'Medical Officer On Duty' }}</td>
    </tr>
    <tr>
        <th>Presenting Complaint / Condition:</th>
        <td colspan="3">{{ $prescription->note ?? 'Patient presented with clinical symptoms requiring medical evaluation, diagnostic investigation, and treatment.' }}</td>
    </tr>
    <tr>
        <th>Selected Common Illness:</th>
        <td><strong>{{ $prescription->illness_name ?? 'General Medical Consultation' }}</strong></td>
        <th>Urgency Level:</th>
        <td><span class="badge badge-pending">Routine Consultation</span></td>
    </tr>
</table>

<div class="section-title">FOUNDATION AUTHORIZATION</div>
<table class="info-table" style="margin-bottom: 20px;">
    <tr>
        <th>Authorized By:</th>
        <td>{{ $prescription->user?->name ?? 'GOF Healthcare Coordinator' }}</td>
        <th>Position / Role:</th>
        <td>{{ $prescription->user?->roles->first()?->name ? ucwords(str_replace('_', ' ', $prescription->user->roles->first()->name)) : 'Zone Coordinator' }}</td>
    </tr>
    <tr>
        <th>Authorization Signature:</th>
        <td style="height: 35px; vertical-align: bottom;">_______________________________</td>
        <th>Date Authorized:</th>
        <td>{{ $prescription->created_at?->format('d M Y') ?? now()->format('d M Y') }}</td>
    </tr>
</table>

<div style="border-top: 2px dashed #94a3b8; margin: 20px 0;"></div>

<div class="section-title" style="background-color: #0f172a; color: #ffffff; padding: 5px 8px; border-radius: 4px; font-size: 9.5pt;">
    FOR MEDICAL OFFICER USE ONLY
</div>

<table class="data-table" style="margin-bottom: 15px;">
    <tr>
        <td style="width: 32%; font-weight: bold; background-color: #f8fafc;">Clinical Findings:</td>
        <td style="height: 35px; vertical-align: top;"></td>
    </tr>
    <tr>
        <td style="font-weight: bold; background-color: #f8fafc;">Confirmed Diagnosis:</td>
        <td style="height: 30px; vertical-align: top;">{{ $prescription->illness_name }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold; background-color: #f8fafc;">Investigations Requested:</td>
        <td style="height: 30px; vertical-align: top;"></td>
    </tr>
    <tr>
        <td style="font-weight: bold; background-color: #f8fafc;">Lab Tests & Costs:</td>
        <td style="height: 30px; vertical-align: top;">
            @if($prescription->lab_test_cost > 0)
                Lab Test Charge: ₦{{ number_format($prescription->lab_test_cost, 2) }}
            @endif
        </td>
    </tr>
    <tr>
        <td style="font-weight: bold; background-color: #f8fafc;">Medications Prescribed:</td>
        <td style="vertical-align: top; min-height: 40px;">
            @if($prescription->medications->isNotEmpty())
                <ul style="margin: 0; padding-left: 15px;">
                    @foreach($prescription->medications as $med)
                        <li>{{ $med->name }} ({{ $med->type }}) - {{ $med->pivot->dosage ?? 'As prescribed' }}</li>
                    @endforeach
                </ul>
            @endif
        </td>
    </tr>
    <tr>
        <td style="font-weight: bold; background-color: #f8fafc;">Recommended Treatment:</td>
        <td style="height: 35px; vertical-align: top;">{{ $prescription->treatment_notes }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold; background-color: #f8fafc;">Total Cost:</td>
        <td style="font-weight: bold; color: #047857;">
            ₦{{ number_format($prescription->total_cost, 2) }}
        </td>
    </tr>
    <tr>
        <td style="font-weight: bold; background-color: #f8fafc;">Follow-up Date:</td>
        <td></td>
    </tr>
</table>

<table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
    <tr>
        <td style="width: 50%;">
            <div style="font-size: 8.5pt; font-weight: bold; color: #475569;">Attending Doctor Name:</div>
            <div style="font-size: 10pt; color: #0f172a; margin-top: 4px;">{{ $prescription->doctor_name ?? 'Dr. _______________________' }}</div>
        </td>
        <td style="width: 50%; text-align: right;">
            <div style="font-size: 8.5pt; font-weight: bold; color: #475569;">Medical Officer Signature & Stamp:</div>
            <div style="margin-top: 25px;">___________________________________</div>
        </td>
    </tr>
</table>
@endsection
