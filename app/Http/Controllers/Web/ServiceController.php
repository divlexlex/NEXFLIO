<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index()
    {
        return view('admin.services.index', [
            'services' => Service::orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        Service::create($validated);

        return back()->with('success', 'Service added.');
    }

    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        // Never deleted — retiring a service is a status change.
        $service->update($this->validated($request));

        return back()->with('success', 'Service updated.');
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
        ]);
    }
}
