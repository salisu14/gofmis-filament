<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class PrescriptionDocumentController extends Controller
{
    public function preview(Prescription $prescription): Response
    {
        $this->authorizeAccess($prescription);

        $prescription->load([
            'prescribable.deceased.zone',
            'illnessModel',
            'medications',
            'user',
            'treatedBy',
        ]);

        $pdf = Pdf::loadView('pdf.prescription-document', [
            'prescription' => $prescription,
        ])->setPaper('a4', 'portrait');

        return response()->make($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="prescription-'.$prescription->id.'.pdf"',
        ]);
    }

    public function download(Prescription $prescription): Response
    {
        $this->authorizeAccess($prescription);

        $prescription->load([
            'prescribable.deceased.zone',
            'illnessModel',
            'medications',
            'user',
            'treatedBy',
        ]);

        $pdf = Pdf::loadView('pdf.prescription-document', [
            'prescription' => $prescription,
        ])->setPaper('a4', 'portrait');

        $filename = 'prescription-'.strtolower(substr($prescription->id, 0, 8)).'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function referralPreview(Prescription $prescription): Response
    {
        $this->authorizeAccess($prescription);

        $prescription->load([
            'prescribable.deceased.zone',
            'illnessModel',
            'medications',
            'user',
            'treatedBy',
        ]);

        $pdf = Pdf::loadView('pdf.medical-referral-document', [
            'prescription' => $prescription,
        ])->setPaper('a4', 'portrait');

        return response()->make($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="medical-referral-'.$prescription->id.'.pdf"',
        ]);
    }

    public function referralDownload(Prescription $prescription): Response
    {
        $this->authorizeAccess($prescription);

        $prescription->load([
            'prescribable.deceased.zone',
            'illnessModel',
            'medications',
            'user',
            'treatedBy',
        ]);

        $pdf = Pdf::loadView('pdf.medical-referral-document', [
            'prescription' => $prescription,
        ])->setPaper('a4', 'portrait');

        $filename = 'medical-referral-'.strtolower(substr($prescription->id, 0, 8)).'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    protected function authorizeAccess(Prescription $prescription): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403, 'Unauthenticated user.');
        }

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return;
        }

        if ($user->isCoordinator()) {
            $patient = $prescription->prescribable;
            $patientZoneId = $patient?->deceased?->zone_id;
            $userZoneId = $user->coordinatedZone?->id;

            if ($userZoneId && $patientZoneId === $userZoneId) {
                return;
            }
        }

        abort(403, 'Unauthorized access to healthcare prescription document.');
    }
}
