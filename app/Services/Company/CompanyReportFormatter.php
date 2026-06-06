<?php

namespace App\Services\Company;

use App\Models\CompanyInformation;
use Illuminate\Support\Facades\Storage;

class CompanyReportFormatter
{
    public function format(CompanyInformation $company): array
    {
        return [
            'name'            => $company->display_name,
            'trading_name'    => CompanyInformation::tradingName(),
            'legal_name'      => $company->company_name,
            'address_lines'   => CompanyInformation::addressLines(),
            'phone'           => $company->phone_no,
            'email'           => $company->email,
            'website'         => $company->website,
            'logo_url'        => $company->logo_url,
            'logo_path'       => $company->logo_path,
            'logo_abs_path'   => $this->absolutePath($company->logo_path),
            'logo_data_uri'   => $this->buildDataUri($company->logo_path),
            'tax_no'          => $company->tax_registration_no,
            'registration_no' => $company->registration_no,
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
}
