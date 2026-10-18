<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HandlesImageUpload;
use App\Http\Controllers\Controller;
use App\Models\Position;
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

        $services = Service::with(['variants', 'positions'])
            ->when($filters['q'] !== '', fn ($query) => $query->where('name', 'like', '%'.$filters['q'].'%'))
            ->when($filters['category'] !== '', fn ($query) => $query->where('category', $filters['category']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // Building blocks for a Packages-category service: every active
        // non-Packages row the admin can tick into the inclusions list.
        $offerableServices = Service::query()
            ->where('status', 'active')
            ->where('category', '!=', 'Packages')
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'price', 'duration_minutes'])
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'category' => $service->category,
                'price' => (float) $service->price,
                'duration_minutes' => (int) $service->duration_minutes,
            ])
            ->values();

        // Staff roles a package can require (id + name + the service
        // category they fulfil — used to auto-suggest from inclusions).
        $positions = Position::query()
            ->orderBy('id')
            ->get(['id', 'name', 'service_category'])
            ->map(fn (Position $position) => [
                'id' => $position->id,
                'name' => $position->name,
                'service_category' => $position->service_category,
            ])
            ->values();

        return view('admin.services.index', [
            'services' => $services,
            'filters' => $filters,
            'hasFilters' => $filters['q'] !== '' || $filters['category'] !== '' || $filters['status'] !== '',
            'categories' => ServiceCatalog::categoryOrder(),
            'statuses' => Service::query()->distinct()->orderBy('status')->pluck('status')->all(),
            'totalServices' => Service::count(),
            'offerableServices' => $offerableServices,
            'positions' => $positions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $positionIds = $this->pullPositionIds($validated);
        $validated = $this->applyImage($request, null, $validated, 'services');

        $this->validateVariants($request);
        $this->assertVariantLabelsUnique($request);

        DB::transaction(function () use ($request, $validated, $positionIds) {
            $service = Service::create($validated);

            $this->syncVariants($request, $service);
            $this->syncPositions($service, $positionIds);
        });

        return back()->with('success', 'Service added.');
    }

    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $validated = $this->validated($request, $service);
        $positionIds = $this->pullPositionIds($validated);

        $this->validateVariants($request);
        $this->assertVariantLabelsUnique($request, $service);

        DB::transaction(function () use ($request, $service, $validated, $positionIds) {
            // Retiring a service via the Status field keeps it out of new bookings while preserving history.
            $service->update($this->applyImage($request, $service, $validated, 'services'));

            $this->syncVariants($request, $service);
            $this->syncPositions($service, $positionIds);
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
        // New services must use a category from the service_categories table;
        // an update keeps its current (possibly legacy) category valid so the
        // edit form never silently rewrites it.
        $allowedCategories = ServiceCatalog::categoryOrder();

        if ($service !== null && ! in_array($service->category, $allowedCategories, true)) {
            $allowedCategories[] = $service->category;
        }

        return $request->validate([
            'name' => 'required|string|max:255',
            'category' => ['required', Rule::in($allowedCategories)],
            'description' => 'nullable|string|max:2000',
            // Package inclusions are only meaningful for the Packages
            // category: required there, optional everywhere else. Stored
            // exactly as typed (one inclusion per line).
            'package_details' => ['nullable', 'string', 'required_if:category,Packages'],
            // Packages must name at least one staff position that can cater
            // them (Facial + Massage packages need both techs, etc.).
            'position_ids' => ['nullable', 'array', 'required_if:category,Packages', 'min:1'],
            'position_ids.*' => ['integer', 'distinct', 'exists:positions,id'],
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:5',
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'badge' => 'nullable|string|max:50',
            'image' => 'nullable|image|max:4096',
        ]);
    }

    /**
     * position_ids is not a services column — pull it out before create/update
     * and return the id list (empty for non-Packages).
     *
     * @param  array<string, mixed>  $validated
     * @return list<int>
     */
    private function pullPositionIds(array &$validated): array
    {
        $ids = array_values(array_map('intval', $validated['position_ids'] ?? []));
        unset($validated['position_ids']);

        if (($validated['category'] ?? null) !== 'Packages') {
            return [];
        }

        return $ids;
    }

    private function syncPositions(Service $service, array $positionIds): void
    {
        $service->positions()->sync($positionIds);
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
