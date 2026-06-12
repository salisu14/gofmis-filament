<?php

namespace App\Services\Company;

use App\Models\CompanyInformation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyReportFormatter
{
    public function format(CompanyInformation $company): array
    {
        $logoPath = $company->logo_path;
        $logoAbsolutePath = $this->absolutePath($logoPath) ?? $this->fallbackLogoPath();
        $addressLines = $this->addressLines($company);

        return [
            'name'            => $company->display_name,
            'display_name'    => $company->display_name,
            'trading_name'    => $company->trading_name ?: $company->company_name,
            'legal_name'      => $company->company_name,
            'address_lines'   => $addressLines,
            'address'         => implode(', ', $addressLines),
            'phone'           => $company->phone_no,
            'mobile'          => $company->mobile_no,
            'email'           => $company->email,
            'website'         => $company->website,
            'logo_url'        => $company->logo_url,
            'logo_path'       => $logoPath,
            'logo_abs_path'   => $logoAbsolutePath,
            'logo_data_uri'   => $this->buildDataUri($logoPath) ?? $this->buildDataUriFromAbsolutePath($logoAbsolutePath),
            'tax_no'          => $company->tax_registration_no,
            'registration_no' => $company->registration_no,
            'footer'          => $this->invoiceFooter($company),
        ];
    }

    public function invoiceFooter(CompanyInformation $company): string
    {
        $parts = array_filter([
            $company->display_name,
            $company->phone_no ? "Tel: {$company->phone_no}" : null,
            $company->email,
            $company->tax_registration_no ? "Tax No: {$company->tax_registration_no}" : null,
        ]);

        return implode(' | ', $parts);
    }

    public function absolutePath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return null;
        }

        if (is_file($path)) {
            return $path;
        }

        $fullPath = Storage::disk('public')->path($path);

        return is_file($fullPath) ? $fullPath : null;
    }

    public function buildDataUri(?string $path): ?string
    {
        $absolutePath = $this->absolutePath($path);
        if ($absolutePath === null) {
            return null;
        }

        $mime     = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';
        $contents = @file_get_contents($absolutePath);

        if ($contents === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    private function fallbackLogoPath(): ?string
    {
        foreach ([
            storage_path('app/public/logos/gof_logo.jpeg'),
            public_path('images/garko-logo.png'),
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function addressLines(CompanyInformation $company): array
    {
        return array_values(array_filter([
            $company->address_line_1,
            $company->address_line_2,
            trim(implode(', ', array_filter([
                $company->city,
                trim(implode(' ', array_filter([
                    $company->state_province,
                    $company->postal_code,
                ]))),
            ]))),
            $this->countryName($company->country_code),
        ], fn ($line) => filled($line)));
    }

    private function countryName(?string $countryCode): ?string
    {
        return match (strtoupper((string) $countryCode)) {
            '', 'NGA' => null,
            'NG' => 'Nigeria',
            default => strtoupper((string) $countryCode),
        };
    }

    private function buildDataUriFromAbsolutePath(?string $path): ?string
    {
        if ($path === null || ! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'application/octet-stream';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
