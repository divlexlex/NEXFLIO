<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * Serves uploaded files off the 'public' disk through a Laravel route
 * (GET /files/{path}, named files.show) instead of the public/storage
 * symlink, which does not exist on the production host. Uploads and stored
 * paths are untouched — this only changes how files are READ.
 *
 * Access by path prefix: payment_proofs/* is private (Owner/Manager only,
 * everyone else gets 403); everything else (service/promo/gallery images)
 * is public, guests included. Missing files 404. Traversal segments are
 * rejected before touching the disk; serving itself goes through the disk
 * response helper, never raw file reads.
 */
class UploadedFileController extends Controller
{
    public function show(string $path)
    {
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        if (str_starts_with($path, 'payment_proofs/')) {
            $user = auth()->user();

            if (! $user || ! in_array($user->role_id, [1, 2], true)) {
                abort(403);
            }
        }

        return $disk->response($path);
    }
}
