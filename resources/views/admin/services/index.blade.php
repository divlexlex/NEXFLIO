@extends('layouts.admin')

@section('title', 'Services')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Services</h1>
    <button class="btn btn-spa" data-bs-toggle="modal" data-bs-target="#serviceModal"
            data-mode="create">
        <i class="bi bi-plus-lg me-1"></i>New Service
    </button>
</div>

<div class="card p-3">
    <form method="GET" action="{{ route('admin.services') }}" id="services-filter-form" class="row g-2 align-items-end mb-3">
        <div class="col-md-6">
            <label class="form-label small mb-0" for="filter-q">Search</label>
            <input type="search" id="filter-q" name="q" value="{{ $filters['q'] }}" class="form-control" placeholder="Search service name…" autocomplete="off">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-0" for="filter-category">Category</label>
            <select id="filter-category" name="category" class="form-select">
                <option value="">All Categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category }}" {{ $filters['category'] === $category ? 'selected' : '' }}>{{ $category }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-0" for="filter-status">Status</label>
            <select id="filter-status" name="status" class="form-select">
                <option value="">All Status</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" {{ $filters['status'] === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
    </form>
    <p class="text-muted small mb-2">Showing {{ $services->count() }} of {{ $totalServices }} services</p>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr><th style="width:60px;"></th><th>Name</th><th>Category</th><th>Price</th><th>Duration</th><th>Status</th><th class="text-end"></th></tr>
            </thead>
            <tbody>
                @forelse($services as $service)
                    <tr>
                        <td>@include('admin.partials.thumb', ['url' => $service->image_url])</td>
                        <td>
                            <strong>{{ $service->name }}</strong>
                            <div class="small text-muted" style="max-width: 320px;">{{ $service->description }}</div>
                        </td>
                        <td>{{ $service->category }}</td>
                        <td>₱{{ number_format($service->price, 2) }}</td>
                        <td>{{ $service->duration_minutes }} mins</td>
                        <td>
                            <span class="badge {{ match($service->status) {
                                'active' => 'text-bg-success',
                                'suspended' => 'text-bg-warning',
                                default => 'text-bg-secondary',
                            } }}">{{ ucfirst($service->status) }}</span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal" data-bs-target="#serviceModal"
                                    data-mode="edit"
                                    data-id="{{ $service->id }}"
                                    data-name="{{ $service->name }}"
                                    data-category="{{ $service->category }}"
                                    data-description="{{ $service->description }}"
                                    data-package-details="{{ $service->package_details }}"
                                    data-price="{{ $service->price }}"
                                    data-duration="{{ $service->duration_minutes }}"
                                    data-status="{{ $service->status }}"
                                    data-badge="{{ $service->badge }}"
                                    data-image="{{ $service->image_url }}"
                                    data-variants="{{ $service->variants->map(fn ($v) => ['id' => $v->id, 'label' => $v->label, 'price' => $v->price, 'guests' => $v->guests, 'sort' => $v->sort])->values()->toJson() }}">
                                Edit
                            </button>
                            <form method="POST" action="{{ url('admin/services') }}/{{ $service->id }}"
                                  class="d-inline" onsubmit="return confirm('Remove {{ $service->name }}? This will hide it from bookings but keep past appointment history.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ $hasFilters ? 'No services match the filters.' : 'No services yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- CREATE/EDIT MODAL --}}
<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="serviceForm" class="modal-content" enctype="multipart/form-data">
            @csrf
            <span id="service-method-holder"></span>
            <div class="modal-header">
                <h5 class="modal-title" id="service-modal-title">New Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Name</label>
                    <input name="name" id="svc-name" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Category</label>
                        <select name="category" id="svc-category" class="form-select" required>
                            @foreach($categories as $category)
                                <option value="{{ $category }}">{{ $category }}</option>
                            @endforeach
                            <option value="__new__">+ New category…</option>
                        </select>
                        <div class="form-text">Choose the category this service belongs to.</div>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Status</label>
                        <select name="status" id="svc-status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Price (₱)</label>
                        <input type="number" step="0.01" name="price" id="svc-price" class="form-control" min="0" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Duration (mins)</label>
                        <input type="number" name="duration_minutes" id="svc-duration" class="form-control" min="5" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="svc-description" class="form-control" rows="2"></textarea>
                </div>
                {{-- Package inclusions: visible only for the Packages category
                     (toggle on change + on modal open). Required there via the
                     server rule; one inclusion per line. --}}
                <div class="mb-2 d-none" id="svc-package-details-wrap">
                    <label class="form-label" for="svc-package-details">Package details — what's included</label>
                    <textarea name="package_details" id="svc-package-details" class="form-control" rows="4" placeholder="One inclusion per line"></textarea>
                    <div class="form-text">Shown on the public Packages services. Enter one inclusion per line.</div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Badge <span class="text-muted">(optional)</span></label>
                    <input name="badge" id="svc-badge" class="form-control" list="badges" maxlength="50" placeholder="e.g. New, Best, Most Booked">
                    <datalist id="badges">
                        <option value="New"><option value="Best"><option value="Most Booked"><option value="Premium">
                    </datalist>
                </div>

                <hr class="my-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0 fw-semibold">Package Tiers <span class="text-muted fw-normal">(optional)</span></label>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="svc-add-variant"><i class="bi bi-plus-lg me-1"></i>Add tier</button>
                </div>
                <div class="form-text mt-0 mb-2">
                    Selling this as packages (e.g. Solo / Couple / Barkada)? Add a row per tier — bookings then charge
                    the selected tier's price instead of the Price above. Leave empty for a regular service.
                </div>
                <div id="svc-variants" class="d-flex flex-column gap-2"></div>

                @include('admin.partials.image-field', ['prefix' => 'svc'])
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa" id="service-submit">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- NEW CATEGORY MODAL (used by the service form's Category select):
     creates the category via fetch so the service form keeps its typed
     data; without fetch the plain POST runs and the endpoint redirects
     back with a flash. --}}
<div class="modal fade" id="newServiceCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.service-categories.store') }}" id="newServiceCategoryForm" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">New category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-danger mb-2 d-none" id="new-service-category-error"></p>
                <div class="mb-2">
                    <label class="form-label" for="new-service-category-name">Category name <span class="text-danger">*</span></label>
                    <input name="name" id="new-service-category-name" class="form-control" maxlength="255" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa">Add Category</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const variantsWrap = document.getElementById('svc-variants');
        let variantIndex = 0;

        // Package details are only relevant to the Packages category: the
        // whole block is hidden (and un-required) for every other category.
        function syncPackageDetails() {
            const cat = document.getElementById('svc-category');
            const wrap = document.getElementById('svc-package-details-wrap');
            const details = document.getElementById('svc-package-details');
            if (!cat || !wrap || !details) return;
            const isPackages = cat.value === 'Packages';
            wrap.classList.toggle('d-none', !isPackages);
            details.required = isPackages;
        }
        document.getElementById('svc-category').addEventListener('change', syncPackageDetails);

        function addVariantRow(data) {
            data = data || {};
            const idx = variantIndex++;
            const row = document.createElement('div');
            row.className = 'row g-2 align-items-center';
            row.innerHTML =
                '<div class="col-4"><input name="variants[' + idx + '][label]" class="form-control form-control-sm" placeholder="Label" maxlength="40" required></div>' +
                '<div class="col-3"><input type="number" step="0.01" min="0.01" name="variants[' + idx + '][price]" class="form-control form-control-sm" placeholder="Price" required></div>' +
                '<div class="col-2"><input type="number" min="1" name="variants[' + idx + '][guests]" class="form-control form-control-sm" placeholder="Guests"></div>' +
                '<div class="col-2"><input type="number" min="0" name="variants[' + idx + '][sort]" class="form-control form-control-sm" placeholder="Sort" value="0"></div>' +
                '<div class="col-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger" title="Remove">&times;</button></div>' +
                '<input type="hidden" name="variants[' + idx + '][id]">' +
                '<input type="checkbox" name="variants[' + idx + '][remove]" value="1" class="d-none">';

            row.querySelector('[name="variants[' + idx + '][label]"]').value = data.label || '';
            row.querySelector('[name="variants[' + idx + '][price]"]').value = data.price != null ? data.price : '';
            row.querySelector('[name="variants[' + idx + '][guests]"]').value = data.guests != null ? data.guests : '';
            row.querySelector('[name="variants[' + idx + '][sort]"]').value = data.sort != null ? data.sort : 0;
            row.querySelector('[name="variants[' + idx + '][id]"]').value = data.id != null ? data.id : '';

            row.querySelector('button').addEventListener('click', () => {
                const idInput = row.querySelector('[name="variants[' + idx + '][id]"]');
                if (idInput.value) {
                    // Saved tier: flag for deletion server-side and hide.
                    row.querySelector('[name="variants[' + idx + '][remove]"]').checked = true;
                    row.classList.add('d-none');
                } else {
                    row.remove();
                }
            });

            variantsWrap.appendChild(row);
        }

        document.getElementById('svc-add-variant').addEventListener('click', () => addVariantRow());

        document.getElementById('serviceModal').addEventListener('show.bs.modal', (event) => {
            const btn = event.relatedTarget;
            const form = document.getElementById('serviceForm');
            const methodHolder = document.getElementById('service-method-holder');

            const preview = document.getElementById('svc-image-preview');
            const removeWrap = document.getElementById('svc-remove-wrap');
            const removeBox = document.getElementById('svc-remove-image');

            variantsWrap.innerHTML = '';

            if (btn.dataset.mode === 'edit') {
                form.action = "{{ url('admin/services') }}/" + btn.dataset.id;
                methodHolder.innerHTML = '<input type="hidden" name="_method" value="PATCH">';
                document.getElementById('service-modal-title').textContent = 'Edit Service';
                document.getElementById('svc-name').value = btn.dataset.name;
                const catSelect = document.getElementById('svc-category');
                const currentCategory = btn.dataset.category || '';
                catSelect.querySelectorAll('option[data-legacy]').forEach((o) => o.remove());
                if (currentCategory && ![...catSelect.options].some((o) => o.value === currentCategory)) {
                    const extra = new Option(currentCategory, currentCategory);
                    extra.dataset.legacy = '1';
                    catSelect.add(extra);
                }
                catSelect.value = currentCategory;
                document.getElementById('svc-description').value = btn.dataset.description || '';
                document.getElementById('svc-package-details').value = btn.dataset.packageDetails || '';
                document.getElementById('svc-price').value = btn.dataset.price;
                document.getElementById('svc-duration').value = btn.dataset.duration;
                document.getElementById('svc-status').value = btn.dataset.status;
                document.getElementById('svc-badge').value = btn.dataset.badge || '';
                document.getElementById('svc-image').value = '';
                removeBox.checked = false;
                if (btn.dataset.image) {
                    preview.src = btn.dataset.image;
                    preview.style.display = 'block';
                    removeWrap.style.display = 'block';
                } else {
                    preview.style.display = 'none';
                    removeWrap.style.display = 'none';
                }

                let variants = [];
                try { variants = JSON.parse(btn.dataset.variants || '[]'); } catch (e) { variants = []; }
                variants.forEach(addVariantRow);
                syncPackageDetails();
            } else {
                form.action = "{{ route('admin.services.store') }}";
                methodHolder.innerHTML = '';
                document.getElementById('service-modal-title').textContent = 'New Service';
                form.reset();
                document.getElementById('svc-category').querySelectorAll('option[data-legacy]').forEach((o) => o.remove());
                preview.style.display = 'none';
                removeWrap.style.display = 'none';
                syncPackageDetails();
            }
        });
    })();
</script>
<script>
    // Automatic Services filter bar: selects submit immediately, the search
    // input submits ~500ms after the last keystroke. No buttons — resetting
    // to All/empty re-submits and restores the full list.
    (function () {
        const form = document.getElementById('services-filter-form');
        if (!form) return;
        const search = document.getElementById('filter-q');
        let timer = null;

        form.querySelectorAll('#filter-category, #filter-status').forEach((sel) => {
            sel.addEventListener('change', () => form.submit());
        });

        search.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => form.submit(), 500);
        });

        // Keep the typing flow smooth across reloads: focus back into the
        // search box with the caret at the end of the current text.
        if (search.value) {
            search.focus();
            try { search.setSelectionRange(search.value.length, search.value.length); } catch (e) { /* older browsers */ }
        }
    })();
</script>
<script>
    // "+ New category…" (last option of the service Category select): open the
    // category modal instead of submitting that value, then create via fetch
    // so the service form keeps everything already typed. The new option is
    // appended (before the "+ New" option) and selected.
    (function () {
        const select = document.getElementById('svc-category');
        const modal = document.getElementById('newServiceCategoryModal');
        const form = document.getElementById('newServiceCategoryForm');
        const errorEl = document.getElementById('new-service-category-error');
        if (!select || !modal || !form) return;
        let restoreValue = '';
        let justCreated = false;

        // Remember the pre-change value so cancelling out of the new
        // category modal restores the service's original category.
        select.addEventListener('focus', () => {
            select.dataset.prev = select.value;
        });
        select.addEventListener('change', () => {
            if (select.value !== '__new__') return;
            restoreValue = select.dataset.prev || '';
            justCreated = false;
            select.value = restoreValue;
            errorEl.textContent = '';
            errorEl.classList.add('d-none');
            form.reset();
            new bootstrap.Modal(modal).show();
        });

        modal.addEventListener('hidden.bs.modal', () => {
            // Dismissed without creating: put the original selection back.
            if (!justCreated) select.value = restoreValue;
        });

        form.addEventListener('submit', (event) => {
            // No fetch: let the plain POST run (redirect back + flash).
            if (typeof fetch === 'undefined') return;
            event.preventDefault();

            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch(form.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: new FormData(form),
            })
                .then(async (response) => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const message = (data.errors && (data.errors.name || [])[0])
                            || 'Could not add this category.';
                        throw new Error(message);
                    }
                    return data;
                })
                .then((category) => {
                    let option = Array.from(select.options).find((o) => o.value === category.name);
                    if (!option) {
                        option = document.createElement('option');
                        option.value = category.name;
                        option.textContent = category.name;
                        const plus = select.querySelector('option[value="__new__"]');
                        select.insertBefore(option, plus);
                    }
                    select.value = category.name;
                    justCreated = true;
                    // Programmatic value change doesn't fire `change`; re-sync
                    // the package-details visibility for the new category.
                    select.dispatchEvent(new Event('change'));
                    bootstrap.Modal.getInstance(modal).hide();
                })
                .catch((error) => {
                    errorEl.textContent = error.message;
                    errorEl.classList.remove('d-none');
                });
        });
    })();
</script>
@endpush
