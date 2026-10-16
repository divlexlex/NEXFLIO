<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Support\ServiceCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Staff positions backing the employee-form Position selects (see
 * App\Models\Position). Managers grow the list from inside the Add/Edit
 * Employee form via the "+ New position…" modal, which POSTs here with
 * fetch (JSON) — or as a plain form POST fallback.
 */
class PositionController extends Controller
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
            'name' => ['required', 'string', 'max:100'],
            'service_category' => ['nullable', Rule::in(ServiceCatalog::CATEGORY_ORDER)],
        ]);

        $name = trim((string) $request->input('name', ''));

        // Case-insensitive uniqueness, portable across MySQL and SQLite
        // (a plain unique rule is collation-dependent). Registered as an
        // after-callback so it survives passes() (which resets the bag).
        $validator->after(function ($validator) use ($name) {
            if ($name !== '' && Position::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists()) {
                $validator->errors()->add('name', 'That position already exists.');
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

        $position = Position::create([
            'name' => $name,
            'service_category' => $request->input('service_category') ?: null,
        ]);

        if ($wantsJson) {
            return response()->json($position, 201);
        }

        return back()->with('success', 'Position added.');
    }
}
