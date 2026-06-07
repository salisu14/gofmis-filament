<?php

namespace App\Services\Company;

use App\Models\CompanyInformation;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function __construct(
        private readonly CompanyReportFormatter $formatter,
        private readonly Sanitizer $svgSanitizer,
    ) {}

    public function get(): CompanyInformation
    {
        return CompanyInformation::instance();
    }

    /**
     * Update company information. Form data is expected to have `logo_path`
     * and `favicon_path` set (by Filament's FileUpload after storeLogo /
     * storeFavicon have run).
     */
    public function update(array $data, ?CompanyInformation $company = null): CompanyInformation
    {
        $company        = $company ?? CompanyInformation::instance();
        $oldLogoPath    = $company->logo_path;
        $oldFaviconPath = $company->favicon_path;

        $newLogoPath    = $data['logo_path']    ?? null;
        $newFaviconPath = $data['favicon_path'] ?? null;

        $updateData = array_intersect_key(
            $data,
            array_flip($company->getFillable())
        );

        try {
            $updated = DB::transaction(function () use ($company, $updateData) {
                $company->update($updateData);
                return $company->fresh();
            });
        } catch (Throwable $e) {
            $this->safeDelete(array_filter([$newLogoPath, $newFaviconPath]));
            throw $e;
        }

        if ($oldLogoPath !== $newLogoPath) {
            $this->safeDelete([$oldLogoPath]);
        }
        if ($oldFaviconPath !== $newFaviconPath) {
            $this->safeDelete([$oldFaviconPath]);
        }

        return $updated;
    }

    /**
     * @deprecated Prefer update() unless you have a specific record to target.
     */
    public function updateRecord(CompanyInformation $company, array $data, ?int $businessId = null): CompanyInformation
    {
        return $this->update($data, $company);
    }

    public function reportHeader(): array
    {
        return $this->formatter->format($this->get());
    }

    public function invoiceFooter(): string
    {
        return $this->formatter->invoiceFooter($this->get());
    }

    // ─── File Storage (called by Filament's saveUploadedFileUsing) ─

    public function storeLogo(UploadedFile $file): string
    {
        return $this->storeImage(
            $file,
            self::LOGO_DIRECTORY,
            self::LOGO_ALLOWED_TYPES,
            self::LOGO_MAX_SIZE_KB,
            'logo_path'
        );
    }

    public function storeFavicon(UploadedFile $file): string
    {
        return $this->storeImage(
            $file,
            self::FAVICON_DIRECTORY,
            self::FAVICON_ALLOWED_TYPES,
            self::FAVICON_MAX_SIZE_KB,
            'favicon_path'
        );
    }

    // ─── Internals ────────────────────────────────────────────────

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

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = $file->storeAs($directory, $filename, self::DISK);

        if ($mime === 'image/svg+xml') {
            $this->sanitizeStoredSvg($path, $fieldName);
        }

        return $path;
    }

    private function sanitizeStoredSvg(string $path, string $fieldName): void
    {
        $contents = Storage::disk(self::DISK)->get($path);
        $clean    = $this->svgSanitizer->sanitize($contents);

        if (! is_string($clean) || $clean === '') {
            Storage::disk(self::DISK)->delete($path);
            throw ValidationException::withMessages([
                $fieldName => 'SVG could not be sanitized.',
            ]);
        }

        Storage::disk(self::DISK)->put($path, $clean);
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
