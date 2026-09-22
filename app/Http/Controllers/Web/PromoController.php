<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HandlesImageUpload;
use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromoController extends Controller
{
    use HandlesImageUpload;

    public function index()
    {
        $promos = Promo::with('services')->latest()->get();

        return view('admin.promos.index', [
            // Grouped by the same computed status the public site uses to
            // decide visibility (Promo::displayStatus()) — "Expired"/"Scheduled"
            // are never something Admin sets directly, only a read of the
            // dates below.
            'promosByStatus' => $promos->groupBy(fn (Promo $p) => $p->displayStatus()),
            'promos' => $promos,
        ]);
    }

    public function create()
    {
        return view('admin.promos.create', [
            'services' => Service::where('status', 'active')->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->applyImage($request, null, $this->validated($request), 'promos');
        $serviceIds = $validated['service_ids'] ?? [];
        unset($validated['service_ids']);

        $promo = Promo::create($validated);
        $promo->services()->sync($serviceIds);

        return redirect()->route('admin.promos')->with('success', 'Promotion created.');
    }

    public function edit($id)
    {
        $promo = Promo::with('services')->findOrFail($id);

        return view('admin.promos.edit', [
            'promo' => $promo,
            'services' => Service::where('status', 'active')->orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $promo = Promo::findOrFail($id);

        $validated = $this->applyImage($request, $promo, $this->validated($request), 'promos');
        $serviceIds = $validated['service_ids'] ?? [];
        unset($validated['service_ids']);

        // No hard deletes — retiring a promo is an is_active toggle.
        $promo->update($validated);
        $promo->services()->sync($serviceIds);

        return redirect()->route('admin.promos')->with('success', 'Promotion updated.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'discount_type' => ['required', Rule::in(Promo::TYPES)],
            'discount_value' => ['required_if:discount_type,'.Promo::TYPE_PERCENTAGE.','.Promo::TYPE_FIXED_AMOUNT, 'nullable', 'numeric', 'min:0.01'],
            'price' => ['required_if:discount_type,'.Promo::TYPE_BUNDLE, 'nullable', 'numeric', 'min:0'],
            'service_ids' => ['required_unless:discount_type,'.Promo::TYPE_BUNDLE, 'array'],
            'service_ids.*' => 'exists:services,id',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'nullable|boolean',
            'image' => 'nullable|image|max:4096',
        ], [
            'discount_value.required_if' => 'Enter the discount value.',
            'price.required_if' => 'Enter the bundle price.',
            'service_ids.required_unless' => 'Select at least one service for this discount.',
        ]);

        if ($validated['discount_type'] === Promo::TYPE_PERCENTAGE && $validated['discount_value'] > 100) {
            abort(422, 'A percentage discount cannot exceed 100.');
        }

        // An unchecked checkbox is simply absent from the request.
        $validated['is_active'] = $request->boolean('is_active');

        // Only one of price/discount_value is ever meaningful — keep the
        // other explicitly null so stale values never linger (see
        // Promo::displayPrice()/effectivePriceFor()).
        if ($validated['discount_type'] === Promo::TYPE_BUNDLE) {
            $validated['discount_value'] = null;
        } else {
            $validated['price'] = null;
        }

        return $validated;
    }
}
