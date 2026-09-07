<?php

namespace App\Http\Controllers;

use App\Models\Deceased;
use App\Models\Orphan;
use App\Services\Security\DemoReadOnlyGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class BeneficiaryCertificateController extends Controller
{
    /**
     * Preview a deceased death certificate inline in browser.
     */
    public function previewDeathCertificate(Request $request, Deceased $deceased): Response
    {
        DemoReadOnlyGuard::ensureCanExportSensitiveData();

        $this->authorizeDeceasedAccess($deceased);

        $path = $deceased->death_cert_url;
        $disk = $this->resolveDisk($path);

        if (! $disk) {
            abort(404, 'Death certificate file not found.');
        }

        $filename = $this->makeSafeFilename('Death_Certificate', $deceased->reg_no, (string) $deceased->id, $path);

        return Storage::disk($disk)->response(
            $path,
            $filename,
            ['Content-Disposition' => 'inline; filename="'.$filename.'"'],
            'inline'
        );
    }

    /**
     * Download a deceased death certificate as attachment.
     */
    public function downloadDeathCertificate(Request $request, Deceased $deceased): Response
    {
        DemoReadOnlyGuard::ensureCanExportSensitiveData();

        $this->authorizeDeceasedAccess($deceased);

        $path = $deceased->death_cert_url;
        $disk = $this->resolveDisk($path);

        if (! $disk) {
            abort(404, 'Death certificate file not found.');
        }

        $filename = $this->makeSafeFilename('Death_Certificate', $deceased->reg_no, (string) $deceased->id, $path);

        return Storage::disk($disk)->download($path, $filename);
    }

    /**
     * Preview an orphan birth certificate inline in browser.
     */
    public function previewBirthCertificate(Request $request, Orphan $orphan): Response
    {
        DemoReadOnlyGuard::ensureCanExportSensitiveData();

        $this->authorizeOrphanAccess($orphan);

        $path = $orphan->birth_certificate_path;
        $disk = $this->resolveDisk($path);

        if (! $disk) {
            abort(404, 'Birth certificate file not found.');
        }

        $filename = $this->makeSafeFilename('Birth_Certificate', $orphan->reg_no, (string) $orphan->id, $path);

        return Storage::disk($disk)->response(
            $path,
            $filename,
            ['Content-Disposition' => 'inline; filename="'.$filename.'"'],
            'inline'
        );
    }

    /**
     * Download an orphan birth certificate as attachment.
     */
    public function downloadBirthCertificate(Request $request, Orphan $orphan): Response
    {
        DemoReadOnlyGuard::ensureCanExportSensitiveData();

        $this->authorizeOrphanAccess($orphan);

        $path = $orphan->birth_certificate_path;
        $disk = $this->resolveDisk($path);

        if (! $disk) {
            abort(404, 'Birth certificate file not found.');
        }

        $filename = $this->makeSafeFilename('Birth_Certificate', $orphan->reg_no, (string) $orphan->id, $path);

        return Storage::disk($disk)->download($path, $filename);
    }

    /**
     * Generate a safe, human-readable download filename without path-separator or control characters.
     */
    protected function makeSafeFilename(string $prefix, ?string $identifier, string $id, string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'pdf';
        $rawName = filled($identifier) ? $identifier : str_replace('-', '_', $id);

        $safeName = str_replace(['/', '\\'], '-', $rawName);
        $safeName = preg_replace('/[\x00-\x1F\x7F]/u', '', $safeName);
        $safeName = trim(preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $safeName), '_');

        return $prefix.'_'.$safeName.'.'.$extension;
    }

    /**
     * Resolve the storage disk where the file actually exists.
     * Prefers private 'local' storage; falls back to 'public' if pre-existing file was uploaded there.
     */
    protected function resolveDisk(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (Storage::disk('local')->exists($path)) {
            return 'local';
        }

        if (Storage::disk('public')->exists($path)) {
            return 'public';
        }

        return null;
    }

    /**
     * Authorize access to deceased death certificate based on canonical GOFMIS permission & zone rules.
     */
    protected function authorizeDeceasedAccess(Deceased $deceased): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403, 'Unauthenticated.');
        }

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return;
        }

        if ($user->isCoordinator()) {
            $userZoneId = $user->coordinatedZone?->id;
            if ($userZoneId && $deceased->zone_id === $userZoneId) {
                return;
            }
        }

        if ($user->can('view_orphans') || $user->can('view_widows') || $user->isDemoObserver()) {
            return;
        }

        abort(403, 'Unauthorized access to death certificate.');
    }

    /**
     * Authorize access to orphan birth certificate based on canonical GOFMIS permission & zone rules.
     */
    protected function authorizeOrphanAccess(Orphan $orphan): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403, 'Unauthenticated.');
        }

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return;
        }

        if ($user->isCoordinator()) {
            $userZoneId = $user->coordinatedZone?->id;
            $orphanZoneId = $orphan->zone?->id ?? $orphan->deceased?->zone_id;

            if ($userZoneId && $orphanZoneId === $userZoneId) {
                return;
            }
        }

        if ($user->can('view_orphans') || $user->isDemoObserver()) {
            return;
        }

        abort(403, 'Unauthorized access to birth certificate.');
    }
}
