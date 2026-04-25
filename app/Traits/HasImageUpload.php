<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HasImageUpload
{
    /**
     * Stores the uploaded image and returns the path.
     */
    public function uploadImage(UploadedFile $image, string $folder = 'profiles'): string
    {
        // Generate a unique filename: timestamp + random string + extension
        $filename = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

        // Store in the specified folder (e.g., storage/app/public/orphans)
        $path = $image->storeAs($folder, $filename, 'public');

        return $path;
    }
}
