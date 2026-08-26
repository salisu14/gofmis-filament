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
     */
    public function getProfilePhotoDataUriAttribute(): ?string
    {
        if (blank($this->picture_url)) {
            return null;
        }

        if (str_starts_with($this->picture_url, 'http://') || str_starts_with($this->picture_url, 'https://')) {
            return null;
        }

        $path = Storage::disk('public')->path($this->picture_url);

        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/jpeg';
        $base64 = base64_encode($contents);

        return "data:{$mime};base64,{$base64}";
    }
}
