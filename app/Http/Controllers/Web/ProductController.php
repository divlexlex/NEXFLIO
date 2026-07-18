<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\HandlesImageUpload;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use HandlesImageUpload;

    public function index()
    {
        return view('admin.products.index', [
            'products' => Product::orderBy('category')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Product::create($this->applyImage($request, null, $this->validated($request), 'products'));

        return back()->with('success', 'Product added.');
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        // No hard deletes — retiring a product is a status change.
        $product->update($this->applyImage($request, $product, $this->validated($request), 'products'));

        return back()->with('success', 'Product updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'image' => 'nullable|image|max:4096',
        ]);
    }
}
