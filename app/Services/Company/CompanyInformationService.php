<?php

namespace App\Services\Company;

use App\Models\CompanyInformation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompanyInformationService
{
    private const DISK = 'public';

    private const LOGO_MAX_SIZE_KB    = 2048;
    private const FAVICON_MAX_SIZE_KB = 512;

    private const LOGO_DIRECTORY    = 'company/logos';
    private const FAVICON_DIRECTORY = 'company/favicons';

    private const LOGO_ALLOWED_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/svg+xml',
    ];

    private const FAVICON_ALLOWED_TYPES = [
        'image/x-icon',
        'image/png',
        'image/svg+xml',
        'image/vnd.microsoft.icon',
    ];

    /**
     * Defense-in-depth XSS check for uploaded SVGs. For production, install
     * enshrined/svg-sanitize and use it to rewrite the stored file in place.
     */
    private const SVG_DANGEROUS_PATTERNS = [
        '/<script\b/i',
        '/\bon\w+\s*=/i',
        '/javascript:/i',
        '/<foreignObject\b/i',
        '/<use\b[^>]*\bxlink:href\s*=\s*["\']?\s*data:/i',
    ];

    public function __construct(
        private readonly CompanyReportFormatter $formatter,
    ) {}

    public function get(): CompanyInformation
    {
        return CompanyInformation::instance();
    }

    public function update(array $data): CompanyInformation
    {
        return $this->performUpdate(CompanyInformation::instance(), $data);
    }

    /**
     * Backward-compatible entry point for callers that pass a specific record.
     * Unlike the previous implementation, this now actually updates the passed
     * CompanyInformation (the old version silently dropped the parameter and
     * targeted the singleton, which was a bug for any Filament page that
     * handed in a non-singleton record).
     *
     * @deprecated Prefer update() unless you have a specific record to target.
     */
    public function updateRecord(CompanyInformation $company, array $data, ?int $businessId = null): CompanyInformation
    {
        return $this->performUpdate($company, $data);
    }

    public function reportHeader(): array
    {
        return $this->formatter->format($this->get());
    }

    public function invoiceFooter(): string
    {
        return $this->formatter->invoiceFooter($this->get());
    }

    private function performUpdate(CompanyInformation $company, array $data): CompanyInformation
    {
        $changes = $this->extractFileData($data, $company);

        try {
            $updated = DB::transaction(function () use ($company, $changes) {
                $company->update($changes->updateData);
                return $company->fresh();
            });
        } catch (Throwable $e) {
            $this->safeDelete($changes->filesToCleanupOnFailure);
            throw $e;
        }

        $this->safeDelete($changes->filesToDelete);

        return $updated;
    }

    private function extractFileData(array $data, CompanyInformation $company): CompanyFileChanges
    {
        $updateData              = $data;
        $filesToDelete           = [];
        $filesToCleanupOnFailure = [];

        if ($this->isUploadedFile($updateData, 'logo')) {
            $newPath = $this->storeImage(
                $updateData['logo'],
                self::LOGO_DIRECTORY,
                self::LOGO_ALLOWED_TYPES,
                self::LOGO_MAX_SIZE_KB,
                'logo'
            );
            $updateData['logo_path'] = $newPath;
            $filesToCleanupOnFailure[] = $newPath;
            if ($company->logo_path) {
                $filesToDelete[] = $company->logo_path;
            }
            unset($updateData['logo']);
        }

        if ($this->isUploadedFile($updateData, 'favicon')) {
            $newPath = $this->storeImage(
                $updateData['favicon'],
                self::FAVICON_DIRECTORY,
                self::FAVICON_ALLOWED_TYPES,
                self::FAVICON_MAX_SIZE_KB,
                'favicon'
            );
            $updateData['favicon_path'] = $newPath;
            $filesToCleanupOnFailure[] = $newPath;
            if ($company->favicon_path) {
                $filesToDelete[] = $company->favicon_path;
            }
            unset($updateData['favicon']);
        }

        foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path'] as $key => $pathField) {
            if (($updateData["remove_{$key}"] ?? false) && $company->{$pathField}) {
                $filesToDelete[]         = $company->{$pathField};
                $updateData[$pathField]  = null;
            }
            unset($updateData["remove_{$key}"]);
        }

        $updateData = array_intersect_key(
            $updateData,
            array_flip($company->getFillable())
        );

        return new CompanyFileChanges(
            updateData:              $updateData,
            filesToDelete:           array_values(array_unique(array_filter($filesToDelete))),
            filesToCleanupOnFailure: array_values(array_unique(array_filter($filesToCleanupOnFailure))),
        );
    }

    private function isUploadedFile(array $data, string $key): bool
    {
        return ! empty($data[$key]) && $data[$key] instanceof UploadedFile;
    }

    private function storeImage(
        UploadedFile $file,
        string $directory,
        array $allowedTypes,
        int $maxSizeKb,
        string $fieldName
    ): string {
        $mime = $file->getMimeType();

        if (! in_array($mime, $allowedTypes, true)) {
            throw ValidationException::withMessages([
                $fieldName => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes) . '.',
            ]);
        }

        if ($file->getSize() > $maxSizeKb * 1024) {
            throw ValidationException::withMessages([
                $fieldName => "File must not exceed {$maxSizeKb} KB.",
            ]);
        }

        if ($mime === 'image/svg+xml') {
            $this->assertSafeSvg($file, $fieldName);
        }

        return $file->store($directory, self::DISK);
    }

    private function assertSafeSvg(UploadedFile $file, string $fieldName): void
    {
        $contents = @file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages([
                $fieldName => 'Could not read uploaded SVG for inspection.',
            ]);
        }

        foreach (self::SVG_DANGEROUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $contents)) {
                throw ValidationException::withMessages([
                    $fieldName => 'SVG contains potentially dangerous content.',
                ]);
            }
        }
    }

    private function safeDelete(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            try {
                Storage::disk(self::DISK)->delete($path);
            } catch (Throwable $e) {
                Log::warning('Failed to delete company asset', [
                    'path'  => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
