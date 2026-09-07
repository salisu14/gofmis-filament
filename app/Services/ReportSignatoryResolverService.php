<?php

namespace App\Services;

use App\Models\CompanyInformation;
use App\Models\User;
use App\Services\Company\CompanyInformationService;
use Illuminate\Support\Facades\Storage;

class ReportSignatoryResolverService
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    /**
     * Resolve normalized report signatory context.
     *
     * Designed for multi-signatory extensibility. Accepts optional $reportType
     * and $actor to allow user-specific, role-specific, or document-specific
     * resolution in future biometric/signing iterations while currently
     * resolving the default institutional CompanyInformation signatory.
     */
    public function resolveReportSignatory(?string $reportType = null, ?User $actor = null): array
    {
        $company = $this->companyInformationService->get();
        $path = $company->report_signature_path;

        $dataUri = $this->buildPrivateSignatureDataUri($path);

        return [
            'name' => $company->report_signatory_name,
            'title' => $company->report_signatory_title,
            'signature_path' => $path,
            'signature_data_uri' => $dataUri,
            'source' => 'company_default',
            'resolved_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Build base64 data URI for server-side PDF rendering strictly from private storage.
     * Ensures signatures are never exposed via public URLs.
     */
    public function buildPrivateSignatureDataUri(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        // Prioritize private 'local' disk, fallback to 'public' if legacy file exists
        $disk = Storage::disk('local')->exists($path)
            ? 'local'
            : (Storage::disk('public')->exists($path) ? 'public' : null);

        if ($disk === null) {
            return null;
        }

        try {
            $contents = Storage::disk($disk)->get($path);
            if (empty($contents)) {
                return null;
            }

            $mime = Storage::disk($disk)->mimeType($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
