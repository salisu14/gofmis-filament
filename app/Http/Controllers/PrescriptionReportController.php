<?php

namespace App\Http\Controllers;

use App\Models\Orphan;
use App\Models\Prescription;
use App\Models\Widow;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrescriptionReportController extends Controller
{
    public function exportPdf(Request $request): Response
    {
        \App\Services\Security\DemoReadOnlyGuard::ensureCanExportSensitiveData();

        $user = auth()->user();

        if (! $user || (! $user->isAdmin() && ! $user->isSuperAdmin())) {
            abort(403, 'Unauthorized access to healthcare reports.');
        }

        $startDate = $request->query('start_date') ?? now()->startOfMonth()->toDateString();
        $endDate = $request->query('end_date') ?? now()->endOfMonth()->toDateString();
        $patientType = $request->query('patient_type');
        $zoneId = $request->query('zone_id');
        $status = $request->query('status');

        $query = Prescription::query()
            ->with(['prescribable.deceased.zone', 'illnessModel', 'user'])
            ->whereBetween('prescription_date', [$startDate, $endDate]);

        if ($patientType === 'orphan') {
            $query->where('prescribable_type', Orphan::class);
        } elseif ($patientType === 'widow') {
            $query->where('prescribable_type', Widow::class);
        }

        if ($zoneId) {
            $query->whereHasMorph('prescribable', [Orphan::class, Widow::class], function ($q) use ($zoneId) {
                $q->whereHas('deceased', fn ($d) => $d->where('zone_id', $zoneId));
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $prescriptions = $query->orderBy('prescription_date', 'asc')->get();

        $summary = [
            'total_prescriptions' => $prescriptions->count(),
            'orphan_count' => $prescriptions->where('prescribable_type', Orphan::class)->count(),
            'widow_count' => $prescriptions->where('prescribable_type', Widow::class)->count(),
            'total_lab_cost' => (float) $prescriptions->sum('lab_test_cost'),
            'total_drug_cost' => (float) $prescriptions->sum('drug_cost'),
            'total_healthcare_cost' => (float) $prescriptions->sum(fn ($p) => $p->total_cost),
            'pending_count' => $prescriptions->reject(fn ($p) => $p->isTreated())->count(),
            'treated_count' => $prescriptions->filter(fn ($p) => $p->isTreated())->count(),
        ];

        $pdf = Pdf::loadView('pdf.reports.prescription-period-report', [
            'prescriptions' => $prescriptions,
            'summary' => $summary,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])->setPaper('a4', 'landscape');

        $action = $request->query('action', 'download');
        $filename = 'prescription-report-'.\Carbon\Carbon::parse($startDate)->format('Ymd').'-to-'.\Carbon\Carbon::parse($endDate)->format('Ymd').'.pdf';

        if ($action === 'preview') {
            return response()->make($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
