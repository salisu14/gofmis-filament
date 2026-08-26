@extends('pdf.layouts.official-document', [
    'documentTitle' => 'MEDICAL REFERRAL / CONSULTATION FORM',
    'referenceNo' => 'REF-' . strtoupper(substr(str_replace('-', '', $prescription->id), 0, 8)),
    'subtitle' => 'Referral Date: ' . ($prescription->prescription_date ? $prescription->prescription_date->format('d M Y') : now()->format('d M Y'))
])

@section('content')
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
    $validUntil = $prescription->prescription_date ? $prescription->prescription_date->addDays(14)->format('d M Y') : now()->addDays(14)->format('d M Y');
@endphp

<h3 style="border-bottom: 2px solid #374151; padding-bottom: 3px; margin-top: 10px; margin-bottom: 8px; color: #111827; text-transform: uppercase;">1. Patient Information</h3>
<table class="info-grid">
    <tr>
        <td class="label">Patient Name:</td>
        <td class="value"><strong>{{ $patient?->full_name ?? 'N/A' }}</strong></td>
        <td class="label">Registration No:</td>
        <td class="value"><strong>{{ $patient?->reg_no ?? 'N/A' }}</strong></td>
    </tr>
    <tr>
        <td class="label">Category:</td>
        <td class="value">{{ $categoryLabel }}</td>
        <td class="label">Gender / Age:</td>
        <td class="value">{{ $patient?->gender?->getLabel() ?? ($isOrphan ? 'N/A' : 'Female') }} / {{ $age }}</td>
    </tr>
    <tr>
        <td class="label">Assigned Zone:</td>
        <td class="value">{{ $zone?->name ?? 'N/A' }}</td>
        <td class="label">Deceased Parent:</td>
        <td class="value">{{ $deceased?->full_name ?? 'N/A' }}</td>
    </tr>
    <tr>
        <td class="label">Guardian Contact:</td>
        <td class="value">
            @if($isOrphan)
                {{ $deceased?->guardian_name ?? 'N/A' }} @if($deceased?->guardian_phone) ({{ $deceased->guardian_phone }}) @endif
            @else
                {{ $patient?->phone ?? 'N/A' }}
            @endif
        </td>
        <td class="label">National ID / NIN:</td>
        <td class="value">{{ $patient?->nin ?? 'N/A' }}</td>
    </tr>
</table>

<h3 style="border-bottom: 2px solid #374151; padding-bottom: 3px; margin-top: 15px; margin-bottom: 8px; color: #111827; text-transform: uppercase;">2. Referral Details</h3>
<table class="info-grid">
    <tr>
        <td class="label">Referred Hospital:</td>
        <td class="value" colspan="3"><strong>{{ $prescription->hospital_name ?? 'Specialist Hospital / Primary Healthcare Center' }}</strong></td>
    </tr>
    <tr>
        <td class="label">Suspected Illness:</td>
        <td class="value" colspan="3"><strong>{{ $prescription->illness_name ?? 'General Medical Consultation' }}</strong> <span class="muted">(Suspected / Common Illness, to be confirmed by Medical Officer)</span></td>
    </tr>
    <tr>
        <td class="label">Presenting Complaint:</td>
        <td class="value" colspan="3">{{ $prescription->note ?? 'Patient presented with clinical symptoms requiring medical evaluation, diagnostic investigation, and treatment.' }}</td>
    </tr>
    <tr>
        <td class="label">Urgency Level:</td>
        <td class="value">Routine Consultation</td>
        <td class="label">Referral Validity:</td>
        <td class="value">Valid until {{ $validUntil }}</td>
    </tr>
</table>

<h3 style="border-bottom: 2px solid #374151; padding-bottom: 3px; margin-top: 15px; margin-bottom: 8px; color: #111827; text-transform: uppercase;">3. Foundation Authorization</h3>
<table class="info-grid">
    <tr>
        <td class="label">Authorized By:</td>
        <td class="value">{{ $prescription->user?->name ?? 'Zone Coordinator' }}</td>
        <td class="label">Designation / Role:</td>
        <td class="value">
            @if($prescription->user)
                {{ $prescription->user->roles->first()?->name ? ucwords(str_replace('_', ' ', $prescription->user->roles->first()->name)) : 'Zone Coordinator' }}
            @else
                Healthcare Officer
            @endif
        </td>
    </tr>
    <tr>
        <td class="label">Signature:</td>
        <td class="value" style="height: 30px; vertical-align: bottom;"><span class="muted">_______________________________</span></td>
        <td class="label">Authorization Date:</td>
        <td class="value">{{ $prescription->created_at?->format('d M Y') ?? now()->format('d M Y') }}</td>
    </tr>
</table>

<div style="border-top: 2px dashed #94a3b8; margin: 20px 0;"></div>

<h3 style="background-color: #1f2937; color: #ffffff; padding: 6px 10px; margin-top: 10px; margin-bottom: 8px; font-size: 11px; text-transform: uppercase; border-radius: 3px;">4. For Medical Officer Use Only (attending doctor to fill below)</h3>
<table class="info-grid">
    <tr>
        <td class="label" style="height: 35px; width: 25%;">Consultation Date:</td>
        <td class="value" style="width: 25%;"></td>
        <td class="label" style="width: 25%;">Clinical Findings:</td>
        <td class="value" style="width: 25%;"></td>
    </tr>
    <tr>
        <td class="label" style="height: 35px;">Confirmed Diagnosis:</td>
        <td class="value" colspan="3"><span class="muted">(Please state final diagnosis)</span></td>
    </tr>
    <tr>
        <td class="label" style="height: 40px;">Investigations / Labs:</td>
        <td class="value" colspan="3"><span class="muted">(List requested tests/procedures)</span></td>
    </tr>
    <tr>
        <td class="label" style="height: 45px;">Medications & Dosage:</td>
        <td class="value" colspan="3"><span class="muted">(Drugs, strength, frequency & duration)</span></td>
    </tr>
    <tr>
        <td class="label" style="height: 35px;">Recommended Treatment:</td>
        <td class="value" colspan="3"></td>
    </tr>
    <tr>
        <td class="label" style="height: 35px;">Estimated Lab Cost:</td>
        <td class="value">₦ __________________</td>
        <td class="label">Estimated Drug Cost:</td>
        <td class="value">₦ __________________</td>
    </tr>
    <tr>
        <td class="label" style="height: 35px;">Attending Doctor Name:</td>
        <td class="value">Dr. ___________________________</td>
        <td class="label">Registration No / Stamp:</td>
        <td class="value"></td>
    </tr>
    <tr>
        <td class="label" style="height: 35px;">Doctor's Signature:</td>
        <td class="value">_____________________________</td>
        <td class="label">Follow-up Date:</td>
        <td class="value"></td>
    </tr>
</table>
@endsection
