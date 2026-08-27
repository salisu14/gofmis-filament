<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

trait HasProfilePhoto
{
    /**
     * Get the canonical public URL for the beneficiary's profile photo.
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        if (blank($this->picture_url)) {
            return null;
        }

        if (str_starts_with($this->picture_url, 'http://') || str_starts_with($this->picture_url, 'https://')) {
            return $this->picture_url;
        }

        return Storage::disk('public')->url($this->picture_url);
    }

    /**
     * Get the safe data URI for PDF/report generation.
     *
     * Only a local file that resolves to a real path strictly beneath the
     * public storage disk is ever read. The candidate `picture_url` may not:
     *  - be an absolute filesystem path (/... or drive letters);
     *  - climb above the disk root via .. segments;
     *  - escape the disk root via a symlink (resolved with realpath);
     *  - be a remote URL (no outbound HTTP is ever performed here).
     *
     * Any other case returns null for graceful fallback rather than exposing a
     * filesystem path or making a network request.
     */
    public function getProfilePhotoDataUriAttribute(): ?string
    {
        if (blank($this->picture_url)) {
            return null;
        }

        // Absolute URLs are valid for UI rendering through profile_photo_url,
        // but must never be fetched into a local Data URI here.
        if (str_starts_with($this->picture_url, 'http://') || str_starts_with($this->picture_url, 'https://')) {
            return null;
        }

        // Reject traversal segments and absolute filesystem paths outright.
        if (str_contains($this->picture_url, '..')
            || str_starts_with($this->picture_url, '/')
            || preg_match('/^[a-zA-Z]:[\\\\\\/]/', $this->picture_url)) {
            return null;
        }

        $root = Storage::disk('public')->path('');
        $rootReal = realpath($root);

        if ($rootReal === false) {
            return null;
        }

        $candidate = Storage::disk('public')->path($this->picture_url);
        $candidateReal = realpath($candidate);

        // Missing file, a directory, an unreadable/uncanonicalizable path, or a
        // symlink resolving outside the public disk is rejected.
        if ($candidateReal === false
            || is_dir($candidateReal)
            || $candidateReal === $rootReal
            || ! str_starts_with($candidateReal, $rootReal.DIRECTORY_SEPARATOR)) {
            return null;
        }

        $contents = @file_get_contents($candidateReal);

        if ($contents === false) {
            return null;
        }

        $mime = mime_content_type($candidateReal) ?: 'application/octet-stream';
        $base64 = base64_encode($contents);

        return "data:{$mime};base64,{$base64}";
    }
}
