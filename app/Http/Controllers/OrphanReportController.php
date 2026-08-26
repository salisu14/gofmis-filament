<?php

namespace App\Http\Controllers;

use App\Models\Orphan;
use App\Models\WelfareBeneficiary;
use App\Services\Company\CompanyInformationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class OrphanReportController extends Controller
{
    /**
     * Generate and download the comprehensive orphan dossier / case profile PDF.
     *
     * This is a read-only, print/donor-tier report. Authorization reuses the
     * existing OrphanPolicy.view gate and the Orphan "zone" global scope, so a
     * coordinator can only ever resolve an orphan belonging to their own zone.
     */
    public function download(Request $request, Orphan $orphan)
    {
        Gate::authorize('view', $orphan);

        $orphan->load([
            'deceased.zone.coordinator',
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

        $photoDataUri = $this->safePhotoDataUri($orphan->picture_url);

        $pdf = Pdf::loadView('filament.components.orphan-dossier', [
            'orphan' => $orphan,
            'deceased' => $orphan->deceased,
            'welfare' => $welfare,
            'photo_data_uri' => $photoDataUri,
            'company' => app(CompanyInformationService::class)->reportHeader(),
            'generated_at' => now(),
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = 'Orphan-Report-'.($orphan->reg_no ?? strtoupper(substr($orphan->id, 0, 8))).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Build a safe data URI for the orphan photo, or null when absent / not
     * readable. DomPDF cannot reliably open the public-disk stream wrapper, so
     * a data URI is the stable way to render stored images. We only embed a
     * real stored file — no remote placeholder fetching at generation time.
     */
    protected function safePhotoDataUri(?string $pictureUrl): ?string
    {
        if (blank($pictureUrl)) {
            return null;
        }

        if (str_starts_with($pictureUrl, 'http://') || str_starts_with($pictureUrl, 'https://')) {
            return null;
        }

        $path = Storage::disk('public')->path($pictureUrl);

        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}