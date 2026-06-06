<?php

namespace App\Services\Company;

use App\Models\CompanyInformation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CompanyInformationService
{
    private const LOGO_MAX_SIZE_KB = 2048;
    private const FAVICON_MAX_SIZE_KB = 512;
    private const LOGO_ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/svg+xml', 'image/webp'];
    private const FAVICON_ALLOWED_TYPES = ['image/x-icon', 'image/png', 'image/svg+xml', 'image/vnd.microsoft.icon'];

    /**
     * Get the singleton company profile.
     */
    public function get(): CompanyInformation
    {
        return CompanyInformation::instance();
    }

    /**
     * Update company information with file handling.
     *
     * @throws ValidationException
     */
    public function update(array $data): CompanyInformation
    {
        return DB::transaction(function () use ($data) {
            $company = CompanyInformation::instance();

            $filesToDelete = [];
            $updateData = $this->extractFileData($data, $company, $filesToDelete);

            $company->update($updateData);

            $this->deleteFiles($filesToDelete);

            return $company->fresh();
        });
    }

    /**
     * Update a specific company record directly.
     *
     * @deprecated Use update() instead. Kept for backward compatibility with Filament pages.
     */
    public function updateRecord(CompanyInformation $company, array $data, ?int $businessId = null): CompanyInformation
    {
        return $this->update($data);
    }

    // ─── Report Formatters ─────────────────────────────────────────

    public function reportHeader(): array
    {
        $company = $this->get();

        return [
            'name' => $company->display_name,
            'trading_name' => $company->trading_name,
            'legal_name' => $company->company_name,
            'address_lines' => CompanyInformation::addressLines(),
            'phone' => $company->phone_no,
            'email' => $company->email,
            'website' => $company->website,
            'logo_url' => $company->logo_url,
            'logo_path' => $company->logo_path,
            'logo_abs_path' => $this->absolutePath($company->logo_path),
            'logo_data_uri' => $this->buildDataUri($company->logo_path),
            'tax_no' => $company->tax_registration_no,
            'registration_no' => $company->registration_no,
        ];
    }

    public function invoiceFooter(): string
    {
        $company = $this->get();

        $parts = array_filter([
            $company->display_name,
            $company->phone_no ? "Tel: {$company->phone_no}" : null,
            $company->email,
            $company->tax_registration_no ? "Tax No: {$company->tax_registration_no}" : null,
        ]);

        return implode(' | ', $parts);
    }

    // ─── File Handling ─────────────────────────────────────────────

    private function extractFileData(array $data, CompanyInformation $company, array &$filesToDelete): array
    {
        if (! empty($data['logo']) && $data['logo'] instanceof UploadedFile) {
            $data['logo_path'] = $this->storeFile(
                $data['logo'],
                'company/logos',
                self::LOGO_ALLOWED_TYPES,
                self::LOGO_MAX_SIZE_KB,
                'logo'
            );
            $filesToDelete[] = $company->logo_path;
            unset($data['logo']);
        }

        if (! empty($data['favicon']) && $data['favicon'] instanceof UploadedFile) {
            $data['favicon_path'] = $this->storeFile(
                $data['favicon'],
                'company/favicons',
                self::FAVICON_ALLOWED_TYPES,
                self::FAVICON_MAX_SIZE_KB,
                'favicon'
            );
            $filesToDelete[] = $company->favicon_path;
            unset($data['favicon']);
        }

        if (($data['remove_logo'] ?? false) && $company->logo_path) {
            $filesToDelete[] = $company->logo_path;
            $data['logo_path'] = null;
        }
        unset($data['remove_logo']);

        if (($data['remove_favicon'] ?? false) && $company->favicon_path) {
            $filesToDelete[] = $company->favicon_path;
            $data['favicon_path'] = null;
        }
        unset($data['remove_favicon']);

        return array_intersect_key($data, array_flip((new CompanyInformation)->getFillable()));
    }

    private function storeFile(
        UploadedFile $file,
        string $directory,
        array $allowedTypes,
        int $maxSizeKb,
        string $fieldName
    ): string {
        if (! in_array($file->getMimeType(), $allowedTypes, true)) {
            throw ValidationException::withMessages([
                $fieldName => "Invalid file type. Allowed: " . implode(', ', $allowedTypes),
            ]);
        }

        if ($file->getSize() > $maxSizeKb * 1024) {
            throw ValidationException::withMessages([
                $fieldName => "File must not exceed {$maxSizeKb}KB.",
            ]);
        }

        return $file->store($directory, 'public');
    }

    private function deleteFiles(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function absolutePath(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $fullPath = public_path('storage/' . ltrim($path, '/'));

        return is_file($fullPath) ? $fullPath : null;
    }

    private function buildDataUri(?string $path): ?string
    {
        $absolutePath = $this->absolutePath($path);
        if ($absolutePath === null) {
            return null;
        }

        $mime = mime_content_type($absolutePath) ?: 'application/octet-stream';
        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
