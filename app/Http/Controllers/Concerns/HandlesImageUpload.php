<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Shared image-upload handling for admin CRUD controllers. Stores uploads on
 * the public disk, deletes the previous file when replacing/removing, and
 * folds the resulting `image_path` into the validated attributes.
 */
trait HandlesImageUpload
{
    /**
     * @param  \Illuminate\Database\Eloquent\Model|null  $model  existing row on update, null on create
     */
    protected function applyImage(Request $request, $model, array $validated, string $dir): array
    {
        // Explicit "remove image" (edit form checkbox) clears it back to placeholder.
        if ($request->boolean('remove_image')) {
            $model?->deleteImageFile();
            $validated['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            $model?->deleteImageFile();
            $validated['image_path'] = $request->file('image')->store($dir, 'public');
        }

        // The raw uploaded file isn't a model attribute.
        unset($validated['image']);

        return $validated;
    }
}
