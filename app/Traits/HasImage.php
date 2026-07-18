<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;

/**
 * Adds an `image_url` accessor (appended to JSON) for models that store an
 * uploaded image at `image_path` on the public disk. Returns an absolute URL,
 * or null when no image is set so clients can fall back to a placeholder.
 */
trait HasImage
{
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image_path)) {
            return null;
        }

        return asset('storage/' . ltrim($this->image_path, '/'));
    }

    /**
     * Deletes the stored image file (if any) from the public disk. Used before
     * replacing or clearing an image so orphaned files don't pile up.
     */
    public function deleteImageFile(): void
    {
        if (!empty($this->image_path) && Storage::disk('public')->exists($this->image_path)) {
            Storage::disk('public')->delete($this->image_path);
        }
    }
}
