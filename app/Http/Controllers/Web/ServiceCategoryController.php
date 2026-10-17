<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Service categories backing the Services page's filter and New/Edit Service
 * form selects (see App\Models\ServiceCategory). Managers grow the list from
 * inside the form via the "+ New category…" option, which POSTs here with
 * fetch (JSON) — or as a plain form POST fallback.
 */
class ServiceCategoryController extends Controller
{
    public function store(Request $request)
    {
        // NOTE: bootstrap/app.php renders JSON error responses only for
        // api/*, so a web-route ValidationException always redirects —
        // even for fetch calls. Branch on the Accept header directly so
        // the modal's AJAX path gets JSON (errors included) while the
        // plain-POST fallback keeps the redirect + flash behaviour.
        $wantsJson = str_contains($request->header('Accept', ''), '/json')
            || str_contains($request->header('Accept', ''), '+json');

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim((string) $request->input('name', ''));

        // Case-insensitive uniqueness, portable across MySQL and SQLite
        // (a plain unique rule is collation-dependent). Registered as an
        // after-callback so it survives passes() (which resets the bag).
        $validator->after(function ($validator) use ($name) {
            if ($name !== '' && ServiceCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
                $validator->errors()->add('name', 'That category already exists.');
            }
        });

        if ($validator->fails()) {
            if ($wantsJson) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $validator->errors()->toArray(),
                ], 422);
            }

            throw new ValidationException($validator);
        }

        $category = ServiceCategory::create(['name' => $name]);

        if ($wantsJson) {
            return response()->json($category, 201);
        }

        return back()->with('success', 'Category added.');
    }
}
