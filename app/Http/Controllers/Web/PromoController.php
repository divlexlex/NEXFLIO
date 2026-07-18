<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HandlesImageUpload;
use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Service;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    use HandlesImageUpload;

    public function index()
    {
        return view('admin.promos.index', [
            'promos' => Promo::with('service')->orderByDesc('is_active')->latest()->get(),
            'services' => Service::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Promo::create($this->applyImage($request, null, $this->validated($request), 'promos'));

        return back()->with('success', 'Promo added.');
    }

    public function update(Request $request, $id)
    {
        $promo = Promo::findOrFail($id);

        // No hard deletes — retiring a promo is an is_active toggle.
        $promo->update($this->applyImage($request, $promo, $this->validated($request), 'promos'));

        return back()->with('success', 'Promo updated.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'service_id' => 'nullable|exists:services,id',
            'is_active' => 'nullable|boolean',
            'image' => 'nullable|image|max:4096',
        ]);

        // An unchecked checkbox is simply absent from the request.
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
