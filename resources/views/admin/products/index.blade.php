@extends('layouts.admin')

@section('title', 'Products')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Products</h1>
        <p class="text-muted small mb-0">Retail items shown in the app's Shop and on the website.</p>
    </div>
    <button class="btn btn-spa" data-bs-toggle="modal" data-bs-target="#productModal" data-mode="create">
        <i class="bi bi-plus-lg me-1"></i>New Product
    </button>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr><th style="width:60px;"></th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th class="text-end"></th></tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr>
                        <td>@include('admin.partials.thumb', ['url' => $product->image_url])</td>
                        <td>
                            <strong>{{ $product->name }}</strong>
                            <div class="small text-muted" style="max-width: 320px;">{{ $product->description }}</div>
                        </td>
                        <td>{{ $product->category ?: '—' }}</td>
                        <td>₱{{ number_format($product->price, 2) }}</td>
                        <td>{{ $product->stock }}</td>
                        <td>
                            <span class="badge {{ $product->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ ucfirst($product->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal" data-bs-target="#productModal"
                                    data-mode="edit"
                                    data-id="{{ $product->id }}"
                                    data-name="{{ $product->name }}"
                                    data-category="{{ $product->category }}"
                                    data-description="{{ $product->description }}"
                                    data-price="{{ $product->price }}"
                                    data-stock="{{ $product->stock }}"
                                    data-status="{{ $product->status }}"
                                    data-image="{{ $product->image_url }}">
                                Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No products yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- CREATE/EDIT MODAL --}}
<div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="productForm" class="modal-content" enctype="multipart/form-data">
            @csrf
            <span id="product-method-holder"></span>
            <div class="modal-header">
                <h5 class="modal-title" id="product-modal-title">New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Name</label>
                    <input name="name" id="prod-name" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Category</label>
                        <input name="category" id="prod-category" class="form-control" list="prod-categories">
                        <datalist id="prod-categories">
                            <option value="Nail Care"><option value="Skincare"><option value="Tools"><option value="Gift Sets">
                        </datalist>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Status</label>
                        <select name="status" id="prod-status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Price (₱)</label>
                        <input type="number" step="0.01" name="price" id="prod-price" class="form-control" min="0" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Stock</label>
                        <input type="number" name="stock" id="prod-stock" class="form-control" min="0" value="0" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="prod-description" class="form-control" rows="2"></textarea>
                </div>
                @include('admin.partials.image-field', ['prefix' => 'prod'])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('productModal').addEventListener('show.bs.modal', (event) => {
        const btn = event.relatedTarget;
        const form = document.getElementById('productForm');
        const methodHolder = document.getElementById('product-method-holder');
        const preview = document.getElementById('prod-image-preview');
        const removeWrap = document.getElementById('prod-remove-wrap');
        const removeBox = document.getElementById('prod-remove-image');

        if (btn.dataset.mode === 'edit') {
            form.action = "{{ url('admin/products') }}/" + btn.dataset.id;
            methodHolder.innerHTML = '<input type="hidden" name="_method" value="PATCH">';
            document.getElementById('product-modal-title').textContent = 'Edit Product';
            document.getElementById('prod-name').value = btn.dataset.name;
            document.getElementById('prod-category').value = btn.dataset.category || '';
            document.getElementById('prod-description').value = btn.dataset.description || '';
            document.getElementById('prod-price').value = btn.dataset.price;
            document.getElementById('prod-stock').value = btn.dataset.stock;
            document.getElementById('prod-status').value = btn.dataset.status;
            document.getElementById('prod-image').value = '';
            removeBox.checked = false;
            if (btn.dataset.image) {
                preview.src = btn.dataset.image;
                preview.style.display = 'block';
                removeWrap.style.display = 'block';
            } else {
                preview.style.display = 'none';
                removeWrap.style.display = 'none';
            }
        } else {
            form.action = "{{ route('admin.products.store') }}";
            methodHolder.innerHTML = '';
            document.getElementById('product-modal-title').textContent = 'New Product';
            form.reset();
            preview.style.display = 'none';
            removeWrap.style.display = 'none';
        }
    });
</script>
@endpush
