<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HandlesImageUpload;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Support\ServiceCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceController extends Controller
{
    use HandlesImageUpload;

    public function index(Request $request)
    {
        // The category dropdown persists per session (search `q` and the
        // status dropdown never do): WITH the param uses + stores it,
        // WITHOUT it the stored choice applies, then show-all.
        $category = trim((string) $request->query('category', $request->session()->get('filters.services.category', '')));
        if ($request->query('category') !== null) {
            $request->session()->put('filters.services.category', $category);
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'category' => $category,
            'status' => trim((string) $request->query('status', '')),
        ];

        $services = Service::with('variants')
            ->when($filters['q'] !== '', fn ($query) => $query->where('name', 'like', '%'.$filters['q'].'%'))
            ->when($filters['category'] !== '', fn ($query) => $query->where('category', $filters['category']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('admin.services.index', [
            'services' => $services,
            'filters' => $filters,
            'hasFilters' => $filters['q'] !== '' || $filters['category'] !== '' || $filters['status'] !== '',
            'categories' => ServiceCatalog::CATEGORY_ORDER,
            'statuses' => Service::query()->distinct()->orderBy('status')->pluck('status')->all(),
            'totalServices' => Service::count(),
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

        $validated = $this->validated($request, $service);

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

    private function validated(Request $request, ?Service $service = null): array
    {
        // New services must use a canonical category; an update keeps its
        // current (possibly legacy) category valid so the edit form never
        // silently rewrites it.
        $allowedCategories = ServiceCatalog::CATEGORY_ORDER;

        if ($service !== null && ! in_array($service->category, $allowedCategories, true)) {
            $allowedCategories[] = $service->category;
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::in($allowedCategories)],
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
