<?php

namespace App\Services;

use App\Services\Company\CompanyInformationService;

class DocumentBrandingService
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    /**
     * Get a standardized document branding context array using CompanyInformation.
     */
    public function getDocumentContext(?string $documentTitle = null, ?string $referenceNo = null): array
    {
        $header = $this->companyInformationService->reportHeader();

        return [
            'organisation_name' => $header['name'] ?? 'Garko Orphans Foundation',
            'display_name' => $header['display_name'] ?? 'Garko Orphans Foundation',
            'legal_name' => $header['legal_name'] ?? 'Garko Orphans Foundation',
            'trading_name' => $header['trading_name'] ?? 'Garko Orphans Foundation',
            'address' => $header['address'] ?? '',
            'address_lines' => $header['address_lines'] ?? [],
            'phone' => $header['phone'] ?? $header['mobile'] ?? '',
            'email' => $header['email'] ?? '',
            'website' => $header['website'] ?? '',
            'registration_number' => $header['registration_no'] ?? $header['tax_no'] ?? '',
            'logo_url' => $header['logo_url'] ?? null,
            'logo_path' => $header['logo_path'] ?? null,
            'logo_abs_path' => $header['logo_abs_path'] ?? null,
            'logo_data_uri' => $header['logo_data_uri'] ?? null,
            'footer_text' => $header['footer'] ?? '',
            'document_title' => $documentTitle,
            'reference_no' => $referenceNo,
            'generated_at' => now(),
            'generated_by' => auth()->user()?->name ?? 'System',
        ];
    }
}
