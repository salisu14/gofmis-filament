<?php

namespace App\Http\Controllers;

use App\Models\Orphan;
use App\Models\WelfareBeneficiary;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrphanReportController extends Controller
{
    /**
     * Generate and download the comprehensive orphan dossier / case profile PDF.
     *
     * Authorization checks gate permissions and enforces zone isolation for coordinators.
     */
    public function download(Request $request, Orphan $orphan)
    {
        \App\Services\Security\DemoReadOnlyGuard::ensureCanExportSensitiveData();

        Gate::authorize('view', $orphan);

        $user = auth()->user();
        if ($user && ! $user->hasAnyRole(['admin', 'super_admin'])) {
            $userZoneId = $user->coordinatedZone?->id;
            $orphanZoneId = $orphan->deceased?->zone_id;
            if (! $userZoneId || $userZoneId !== $orphanZoneId) {
                abort(403, 'Unauthorized zone access.');
            }
        }

        $orphan->load([
            'deceased.zone.coordinator',
            'deceased.widows',
            'educations.institution',
            'educations.orphanClass',
            'prescriptions.illnessModel',
            'prescriptions.medications',
            'interventionRequests.type',
            'interventionRequests.interventions',
            'interventions.type',
            'sponsorships.sponsor',
        ]);

        // Household welfare support is household-level (WelfareBeneficiary is
        // keyed to the deceased household), so present it explicitly as such.
        $welfare = WelfareBeneficiary::query()
            ->with(['welfarePackage.items.item'])
            ->where('deceased_id', $orphan->deceased_id)
            ->orderBy('created_at')
            ->get();

        $photoDataUri = $orphan->profile_photo_data_uri;

        $companyService = app(\App\Services\Company\CompanyInformationService::class);
        $company = $companyService->reportHeader();

        $pdf = Pdf::loadView('filament.components.orphan-dossier', [
            'orphan' => $orphan,
            'deceased' => $orphan->deceased,
            'welfare' => $welfare,
            'photo_data_uri' => $photoDataUri,
            'company' => $company,
            'generated_at' => now(),
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = 'Orphan-Report-'.($orphan->reg_no ?? strtoupper(substr($orphan->id, 0, 8))).'.pdf';

        return $pdf->download($filename);
    }
}
