@extends('layouts.admin')

@section('title', 'Edit Promotion')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Edit Promotion</h1>
    <p class="text-muted small mb-0">{{ $promo->title }}</p>
</div>

<form method="POST" action="{{ route('admin.promos.update', $promo->id) }}" enctype="multipart/form-data">
    @csrf
    @method('PATCH')

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card p-4 mb-3">
        <h2 class="h5 mb-3">Basic Information</h2>
        <div class="mb-3">
            <label class="form-label">Promotion Title <span class="text-danger">*</span></label>
            <input name="title" class="form-control" value="{{ old('title', $promo->title) }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Description <span class="text-muted">(optional)</span></label>
            <textarea name="description" class="form-control" rows="3">{{ old('description', $promo->description) }}</textarea>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Promotion Type <span class="text-danger">*</span></label>
                <select name="discount_type" id="discountType" class="form-select" required>
                    <option value="percentage" {{ old('discount_type', $promo->discount_type) === 'percentage' ? 'selected' : '' }}>Percentage Discount</option>
                    <option value="fixed_amount" {{ old('discount_type', $promo->discount_type) === 'fixed_amount' ? 'selected' : '' }}>Fixed Amount Discount</option>
                    <option value="bundle" {{ old('discount_type', $promo->discount_type) === 'bundle' ? 'selected' : '' }}>Bundle (flat price)</option>
                </select>
            </div>
            <div class="col-md-6 mb-3" id="discountValueWrap">
                <label class="form-label">Discount Value <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" step="0.01" min="0" name="discount_value" id="discountValue" class="form-control" value="{{ old('discount_value', $promo->discount_value) }}">
                    <span class="input-group-text" id="discountValueSuffix">%</span>
                </div>
            </div>
            <div class="col-md-6 mb-3 d-none" id="priceWrap">
                <label class="form-label">Bundle Price (₱) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" name="price" id="price" class="form-control" value="{{ old('price', $promo->price) }}">
            </div>
        </div>
        @include('admin.partials.image-field', ['prefix' => 'promo'])
        @if($promo->image_url)
            <script>document.addEventListener('DOMContentLoaded', () => {
                const preview = document.getElementById('promo-image-preview');
                preview.src = @json($promo->image_url);
                preview.style.display = 'block';
                document.getElementById('promo-remove-wrap').style.display = 'block';
            });</script>
        @endif
    </div>

    <div class="card p-4 mb-3">
        <h2 class="h5 mb-1">Select Services</h2>
        <p class="text-muted small mb-3">Pick the services this promotion applies to.</p>
        <input type="search" id="serviceSearch" class="form-control mb-2" placeholder="Search services...">
        <div class="border rounded p-2" style="max-height: 300px; overflow-y: auto;">
            @php($selectedIds = old('service_ids', $promo->services->pluck('id')->all()))
            @foreach($services as $service)
                <div class="form-check py-1" data-service-row data-name="{{ strtolower($service->name) }}">
                    <input class="form-check-input" type="checkbox" name="service_ids[]"
                           value="{{ $service->id }}" id="svc-check-{{ $service->id }}"
                           {{ in_array($service->id, $selectedIds) ? 'checked' : '' }}>
                    <label class="form-check-label" for="svc-check-{{ $service->id }}">
                        {{ $service->name }} <span class="text-muted small">(₱{{ number_format($service->price, 2) }})</span>
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card p-4 mb-3">
        <h2 class="h5 mb-3">Schedule &amp; Visibility</h2>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Start Date <span class="text-muted">(optional)</span></label>
                <input type="date" name="starts_at" class="form-control" value="{{ old('starts_at', $promo->starts_at?->toDateString()) }}">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">End Date <span class="text-muted">(optional)</span></label>
                <input type="date" name="ends_at" class="form-control" value="{{ old('ends_at', $promo->ends_at?->toDateString()) }}">
            </div>
        </div>
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" {{ old('is_active', $promo->is_active) ? 'checked' : '' }}>
            <label class="form-check-label" for="isActive">Show on Website</label>
        </div>
        <p class="text-muted small mb-0">
            Current status: <strong>{{ ucfirst($promo->displayStatus()) }}</strong> — "Scheduled"/"Expired" follow the dates above automatically.
        </p>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('admin.promos') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-spa">Save Changes</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const typeSelect = document.getElementById('discountType');
    const discountValueWrap = document.getElementById('discountValueWrap');
    const discountValueInput = document.getElementById('discountValue');
    const discountSuffix = document.getElementById('discountValueSuffix');
    const priceWrap = document.getElementById('priceWrap');
    const priceInput = document.getElementById('price');

    function syncDiscountFields() {
        const type = typeSelect.value;
        if (type === 'bundle') {
            discountValueWrap.classList.add('d-none');
            discountValueInput.required = false;
            priceWrap.classList.remove('d-none');
            priceInput.required = true;
        } else {
            discountValueWrap.classList.remove('d-none');
            discountValueInput.required = true;
            priceWrap.classList.add('d-none');
            priceInput.required = false;
            discountSuffix.textContent = type === 'percentage' ? '%' : '₱';
        }
    }
    typeSelect.addEventListener('change', syncDiscountFields);
    syncDiscountFields();

    const searchInput = document.getElementById('serviceSearch');
    searchInput.addEventListener('input', () => {
        const term = searchInput.value.trim().toLowerCase();
        document.querySelectorAll('[data-service-row]').forEach(row => {
            row.style.display = !term || row.dataset.name.includes(term) ? '' : 'none';
        });
    });
})();
</script>
@endpush
