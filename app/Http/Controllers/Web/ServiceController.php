<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HandlesImageUpload;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    use HandlesImageUpload;

    public function index()
    {
        return view('admin.services.index', [
            'services' => Service::with('variants')->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->applyImage($request, null, $this->validated($request), 'services');

        $this->validateVariants($request);
        $this->assertVariantLabelsUnique($request);

        DB::transaction(function () use ($request, $validated) {
            $service = Service::create($validated);

            $this->syncVariants($request, $service);
        });

        return back()->with('success', 'Service added.');
    }

    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $validated = $this->validated($request);

        $this->validateVariants($request);
        $this->assertVariantLabelsUnique($request, $service);

        DB::transaction(function () use ($request, $service, $validated) {
            // Retiring a service via the Status field keeps it out of new bookings while preserving history.
            $service->update($this->applyImage($request, $service, $validated, 'services'));

            $this->syncVariants($request, $service);
        });

        return back()->with('success', 'Service updated.');
    }

    public function destroy($id)
    {
        Service::findOrFail($id)->delete();

        return back()->with('success', 'Service removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:5',
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'badge' => 'nullable|string|max:50',
            'image' => 'nullable|image|max:4096',
        ]);
    }

    /**
     * Optional package tiers (Solo / Couple / Barkada). Rows arrive as
     * variants[N][label|price|guests|sort|id|remove]; "remove" is set for a
     * saved tier the admin deleted. Empty for regular services.
     */
    private function validateVariants(Request $request): void
    {
        $request->validate([
            'variants' => 'nullable|array',
            'variants.*.id' => 'nullable|integer',
            'variants.*.label' => 'required|string|max:40',
            'variants.*.price' => 'required|numeric|min:0.01',
            'variants.*.guests' => 'nullable|integer|min:1|max:255',
            'variants.*.sort' => 'nullable|integer|min:0',
            'variants.*.remove' => 'nullable|in:1',
        ]);
    }

    /**
     * Labels must be unique per service (case-insensitive) — both among the
     * rows being saved and against tiers already on the service.
     */
    private function assertVariantLabelsUnique(Request $request, ?Service $service = null): void
    {
        $seen = [];

        foreach ($request->input('variants', []) as $row) {
            if (! empty($row['remove'])) {
                continue;
            }

            $label = strtolower(trim((string) ($row['label'] ?? '')));

            if ($label === '') {
                continue; // the required rule already reported it
            }

            if (isset($seen[$label])) {
                throw ValidationException::withMessages([
                    'variants' => 'Each package tier needs a unique label.',
                ]);
            }
            $seen[$label] = true;

            if ($service) {
                $conflict = ServiceVariant::where('service_id', $service->id)
                    ->whereRaw('LOWER(label) = ?', [$label])
                    ->when(! empty($row['id']), fn ($query) => $query->whereKeyNot($row['id']))
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'variants' => 'A package tier with that label already exists for this service.',
                    ]);
                }
            }
        }
    }

    private function syncVariants(Request $request, Service $service): void
    {
        foreach ($request->input('variants', []) as $row) {
            if (! empty($row['remove'])) {
                if (! empty($row['id'])) {
                    $service->variants()->whereKey($row['id'])->delete();
                }

                continue;
            }

            $data = [
                'label' => trim((string) $row['label']),
                'price' => $row['price'],
                'guests' => isset($row['guests']) && $row['guests'] !== '' ? (int) $row['guests'] : null,
                'sort' => isset($row['sort']) && $row['sort'] !== '' ? (int) $row['sort'] : 0,
            ];

            if (! empty($row['id'])) {
                // whereKey() keeps a tampered id from touching another
                // service's tier — the relation already scopes service_id.
                $service->variants()->whereKey($row['id'])->update($data);
            } else {
                $service->variants()->create($data);
            }
        }
    }
}
