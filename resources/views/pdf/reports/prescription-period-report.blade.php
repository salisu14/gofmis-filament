@extends('pdf.layouts.official-document', [
    'documentTitle' => 'HEALTHCARE PRESCRIPTION PERIOD REPORT',
    'subtitle' => 'Period: ' . \Carbon\Carbon::parse($startDate)->format('d M Y') . ' to ' . \Carbon\Carbon::parse($endDate)->format('d M Y')
])

@section('content')
    <table class="summary-grid">
        <tr>
            <td>
                <div class="lbl">Total Prescriptions</div>
                <div class="num">{{ number_format($summary['total_prescriptions']) }}</div>
            </td>
            <td>
                <div class="lbl">Orphan Prescriptions</div>
                <div class="num">{{ number_format($summary['orphan_count']) }}</div>
            </td>
            <td>
                <div class="lbl">Widow Prescriptions</div>
                <div class="num">{{ number_format($summary['widow_count']) }}</div>
            </td>
            <td>
                <div class="lbl">Treated / Completed</div>
                <div class="num" style="color: #047857;">{{ number_format($summary['treated_count']) }}</div>
            </td>
            <td>
                <div class="lbl">Pending Treatment</div>
                <div class="num" style="color: #D97706;">{{ number_format($summary['pending_count']) }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="lbl">Total Lab Cost</div>
                <div class="num">NGN {{ number_format($summary['total_lab_cost'], 2) }}</div>
            </td>
            <td colspan="2">
                <div class="lbl">Total Drug Cost</div>
                <div class="num">NGN {{ number_format($summary['total_drug_cost'], 2) }}</div>
            </td>
            <td colspan="2">
                <div class="lbl">Total Healthcare Cost</div>
                <div class="num" style="color: #065F46; font-size: 14px;">NGN {{ number_format($summary['total_healthcare_cost'], 2) }}</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 10%;">Date</th>
                <th style="width: 18%;">Patient Name</th>
                <th style="width: 8%;">Category</th>
                <th style="width: 10%;">Reg No</th>
                <th style="width: 10%;">Zone</th>
                <th style="width: 16%;">Diagnosis</th>
                <th style="width: 14%;">Doctor / Hospital</th>
                <th style="width: 10%;" class="right">Total (NGN)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($prescriptions as $index => $rx)
                @php
                    $patient = $rx->prescribable;
                    $isWidow = $rx->prescribable_type === \App\Models\Widow::class;
                    $patientCategory = $isWidow ? 'Widow' : 'Orphan';
                    $zoneName = $patient?->deceased?->zone?->name ?? 'N/A';
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $rx->prescription_date?->format('d M Y') ?? 'N/A' }}</td>
                    <td><strong>{{ $patient?->full_name ?? trim(($patient?->first_name ?? '') . ' ' . ($patient?->last_name ?? '')) ?: 'N/A' }}</strong></td>
                    <td class="center">{{ $patientCategory }}</td>
                    <td>{{ $patient?->reg_no ?? 'N/A' }}</td>
                    <td>{{ $zoneName }}</td>
                    <td>{{ $rx->illness_name ?? 'N/A' }}</td>
                    <td>{{ $rx->doctor_name ?? 'N/A' }}</td>
                    <td class="right"><strong>{{ number_format((float) $rx->total_cost, 2) }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="center muted" style="padding: 16px;">No healthcare prescriptions found for the selected filter parameters and period.</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($prescriptions) > 0)
            <tfoot>
                <tr>
                    <th colspan="8" class="right">Grand Total:</th>
                    <th class="right">NGN {{ number_format($summary['total_healthcare_cost'], 2) }}</th>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
