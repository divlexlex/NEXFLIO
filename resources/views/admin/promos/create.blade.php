@extends('layouts.admin')

@section('title', 'Create Promotion')

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">Create Promotion</h1>
    <p class="text-muted small mb-0">Fill in the details below to create a new promotion.</p>
</div>

{{-- Step indicator --}}
<div class="d-flex align-items-center gap-2 mb-4 flex-wrap" id="promoWizardSteps">
    @foreach(['Basic Info', 'Select Services', 'Schedule & Visibility', 'Review & Publish'] as $i => $label)
        <div class="d-flex align-items-center gap-2 promo-step-indicator" data-step-indicator="{{ $i + 1 }}">
            <span class="promo-step-circle {{ $i === 0 ? 'active' : '' }}">{{ $i + 1 }}</span>
            <span class="small {{ $i === 0 ? 'fw-semibold' : 'text-muted' }}">{{ $label }}</span>
        </div>
        @if(!$loop->last)<i class="bi bi-chevron-right text-muted"></i>@endif
    @endforeach
</div>

<form method="POST" action="{{ route('admin.promos.store') }}" enctype="multipart/form-data" id="promoWizardForm">
    @csrf

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- STEP 1: Basic Info --}}
    <div class="card p-4 promo-wizard-panel" data-panel="1">
        <h2 class="h5 mb-3">Basic Information</h2>
        <div class="mb-3">
            <label class="form-label">Promotion Title <span class="text-danger">*</span></label>
            <input name="title" class="form-control" value="{{ old('title') }}" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Description <span class="text-muted">(optional — inclusions, add-ons, terms)</span></label>
            <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Promotion Type <span class="text-danger">*</span></label>
                <select name="discount_type" id="discountType" class="form-select" required>
                    <option value="percentage" {{ old('discount_type') === 'percentage' ? 'selected' : '' }}>Percentage Discount</option>
                    <option value="fixed_amount" {{ old('discount_type') === 'fixed_amount' ? 'selected' : '' }}>Fixed Amount Discount</option>
                    <option value="bundle" {{ old('discount_type', 'bundle') === 'bundle' ? 'selected' : '' }}>Bundle (flat price)</option>
                </select>
            </div>
            <div class="col-md-6 mb-3" id="discountValueWrap">
                <label class="form-label">Discount Value <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" step="0.01" min="0" name="discount_value" id="discountValue" class="form-control" value="{{ old('discount_value') }}">
                    <span class="input-group-text" id="discountValueSuffix">%</span>
                </div>
            </div>
            <div class="col-md-6 mb-3 d-none" id="priceWrap">
                <label class="form-label">Bundle Price (₱) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" name="price" id="price" class="form-control" value="{{ old('price') }}">
            </div>
        </div>
        @include('admin.partials.image-field', ['prefix' => 'promo'])
        <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-spa promo-step-next" data-next="2">Next <i class="bi bi-arrow-right ms-1"></i></button>
        </div>
    </div>

    {{-- STEP 2: Select Services --}}
    <div class="card p-4 promo-wizard-panel d-none" data-panel="2">
        <h2 class="h5 mb-1">Select Services</h2>
        <p class="text-muted small mb-3">Pick the services this promotion applies to. A discount promo needs at least one.</p>
        <div class="row g-3">
            <div class="col-md-7">
                <input type="search" id="serviceSearch" class="form-control mb-2" placeholder="Search services...">
                <div class="border rounded p-2" style="max-height: 340px; overflow-y: auto;">
                    @foreach($services as $service)
                        <div class="form-check py-1" data-service-row data-name="{{ strtolower($service->name) }}">
                            <input class="form-check-input promo-service-checkbox" type="checkbox" name="service_ids[]"
                                   value="{{ $service->id }}" id="svc-check-{{ $service->id }}"
                                   data-name="{{ $service->name }}" data-price="{{ $service->price }}"
                                   {{ collect(old('service_ids', []))->contains($service->id) ? 'checked' : '' }}>
                            <label class="form-check-label" for="svc-check-{{ $service->id }}">
                                {{ $service->name }} <span class="text-muted small">(₱{{ number_format($service->price, 2) }})</span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-md-5">
                <p class="fw-semibold small mb-2">Selected Items (<span id="selectedCount">0</span>)</p>
                <div id="selectedItemsList" class="border rounded p-2" style="min-height: 120px; max-height: 340px; overflow-y: auto;">
                    <p class="text-muted small mb-0" id="selectedItemsEmpty">No services selected yet.</p>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn-outline-secondary promo-step-back" data-back="1"><i class="bi bi-arrow-left me-1"></i>Back</button>
            <button type="button" class="btn btn-spa promo-step-next" data-next="3">Next <i class="bi bi-arrow-right ms-1"></i></button>
        </div>
    </div>

    {{-- STEP 3: Schedule & Visibility --}}
    <div class="card p-4 promo-wizard-panel d-none" data-panel="3">
        <h2 class="h5 mb-3">Set Schedule &amp; Visibility</h2>
        <p class="text-muted small mb-3">Choose the date range this promotion runs, and whether it shows on the website.</p>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Start Date <span class="text-muted">(optional — blank means starts immediately)</span></label>
                <input type="date" name="starts_at" class="form-control" value="{{ old('starts_at') }}">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">End Date <span class="text-muted">(optional — blank means no end date)</span></label>
                <input type="date" name="ends_at" class="form-control" value="{{ old('ends_at') }}">
            </div>
        </div>
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
            <label class="form-check-label" for="isActive">Show on Website</label>
        </div>
        <p class="text-muted small mb-0">
            "Scheduled" and "Expired" are set automatically from the dates above — turn this off any time to pull the promotion regardless of its dates.
        </p>
        <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn-outline-secondary promo-step-back" data-back="2"><i class="bi bi-arrow-left me-1"></i>Back</button>
            <button type="button" class="btn btn-spa promo-step-next" data-next="4">Next <i class="bi bi-arrow-right ms-1"></i></button>
        </div>
    </div>

    {{-- STEP 4: Review & Publish --}}
    <div class="card p-4 promo-wizard-panel d-none" data-panel="4">
        <h2 class="h5 mb-3">Review and Publish</h2>
        <p class="text-muted small mb-3">Check the details before saving the promotion.</p>
        <dl class="row mb-0">
            <dt class="col-sm-3">Title</dt><dd class="col-sm-9" id="reviewTitle">&mdash;</dd>
            <dt class="col-sm-3">Description</dt><dd class="col-sm-9" id="reviewDescription">&mdash;</dd>
            <dt class="col-sm-3">Type &amp; Value</dt><dd class="col-sm-9" id="reviewType">&mdash;</dd>
            <dt class="col-sm-3">Services</dt><dd class="col-sm-9" id="reviewServices">&mdash;</dd>
            <dt class="col-sm-3">Validity Period</dt><dd class="col-sm-9" id="reviewPeriod">&mdash;</dd>
            <dt class="col-sm-3">Visibility</dt><dd class="col-sm-9" id="reviewVisibility">&mdash;</dd>
        </dl>
        <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn-outline-secondary promo-step-back" data-back="3"><i class="bi bi-arrow-left me-1"></i>Back</button>
            <button type="submit" class="btn btn-spa">Create Promotion</button>
        </div>
    </div>
</form>
@endsection

@push('head')
<style>
    .promo-step-circle {
        display: inline-flex; align-items: center; justify-content: center;
        width: 26px; height: 26px; border-radius: 50%;
        background: var(--nex-table-header-bg); color: var(--nex-text);
        font-size: 13px; font-weight: 600;
    }
    .promo-step-circle.active { background: var(--nex-primary-bg); color: var(--nex-primary-text); }
    .promo-step-circle.done { background: var(--nex-sidebar-bg); color: var(--nex-sidebar-text); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const panels = document.querySelectorAll('.promo-wizard-panel');
    const indicators = document.querySelectorAll('.promo-step-indicator');

    function showStep(step) {
        panels.forEach(p => p.classList.toggle('d-none', p.dataset.panel !== String(step)));
        indicators.forEach(ind => {
            const n = Number(ind.dataset.stepIndicator);
            const circle = ind.querySelector('.promo-step-circle');
            const label = ind.querySelector('span:last-child');
            circle.classList.remove('active', 'done');
            if (n === step) { circle.classList.add('active'); label.classList.add('fw-semibold'); label.classList.remove('text-muted'); }
            else if (n < step) { circle.classList.add('done'); label.classList.remove('fw-semibold'); label.classList.add('text-muted'); }
            else { label.classList.remove('fw-semibold'); label.classList.add('text-muted'); }
        });
        if (step === 4) buildReview();
        window.scrollTo({ top: document.getElementById('promoWizardForm').offsetTop - 20, behavior: 'smooth' });
    }

    document.querySelectorAll('.promo-step-next').forEach(btn => {
        btn.addEventListener('click', () => showStep(Number(btn.dataset.next)));
    });
    document.querySelectorAll('.promo-step-back').forEach(btn => {
        btn.addEventListener('click', () => showStep(Number(btn.dataset.back)));
    });

    // Discount Type toggles which value field is required/shown.
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

    // Selected services basket.
    const checkboxes = document.querySelectorAll('.promo-service-checkbox');
    const selectedList = document.getElementById('selectedItemsList');
    const selectedEmpty = document.getElementById('selectedItemsEmpty');
    const selectedCount = document.getElementById('selectedCount');

    function renderSelected() {
        const checked = Array.from(checkboxes).filter(c => c.checked);
        selectedCount.textContent = checked.length;
        selectedList.querySelectorAll('[data-selected-row]').forEach(el => el.remove());
        selectedEmpty.classList.toggle('d-none', checked.length > 0);
        checked.forEach(c => {
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center py-1 border-bottom';
            row.dataset.selectedRow = c.value;
            row.innerHTML = '<span>' + c.dataset.name + '<br><small class="text-muted">₱' + Number(c.dataset.price).toFixed(2) + '</small></span>' +
                '<button type="button" class="btn btn-sm btn-link text-danger p-0" data-remove="' + c.value + '"><i class="bi bi-x-lg"></i></button>';
            selectedList.appendChild(row);
        });
        selectedList.querySelectorAll('[data-remove]').forEach(btn => {
            btn.addEventListener('click', () => {
                const cb = document.getElementById('svc-check-' + btn.dataset.remove);
                if (cb) { cb.checked = false; renderSelected(); }
            });
        });
    }
    checkboxes.forEach(c => c.addEventListener('change', renderSelected));
    renderSelected();

    // Service search filter.
    const searchInput = document.getElementById('serviceSearch');
    searchInput.addEventListener('input', () => {
        const term = searchInput.value.trim().toLowerCase();
        document.querySelectorAll('[data-service-row]').forEach(row => {
            row.style.display = !term || row.dataset.name.includes(term) ? '' : 'none';
        });
    });

    // Review step.
    function buildReview() {
        document.getElementById('reviewTitle').textContent = document.querySelector('[name="title"]').value || '—';
        document.getElementById('reviewDescription').textContent = document.querySelector('[name="description"]').value || '—';

        const type = typeSelect.value;
        let typeText;
        if (type === 'bundle') {
            typeText = 'Bundle — ₱' + (Number(priceInput.value || 0)).toFixed(2) + ' flat price';
        } else {
            const suffix = type === 'percentage' ? '%' : '₱';
            typeText = (type === 'percentage' ? 'Percentage Discount' : 'Fixed Amount Discount') + ' — ' + suffix + (discountValueInput.value || 0) + (type === 'percentage' ? '' : ' off');
        }
        document.getElementById('reviewType').textContent = typeText;

        const checkedNames = Array.from(checkboxes).filter(c => c.checked).map(c => c.dataset.name);
        document.getElementById('reviewServices').textContent = checkedNames.length ? checkedNames.join(', ') : 'None selected';

        const start = document.querySelector('[name="starts_at"]').value || 'Immediately';
        const end = document.querySelector('[name="ends_at"]').value || 'No end date';
        document.getElementById('reviewPeriod').textContent = start + ' – ' + end;

        document.getElementById('reviewVisibility').textContent = document.getElementById('isActive').checked ? 'Shown on Website' : 'Hidden (Inactive)';
    }
})();
</script>
@endpush
