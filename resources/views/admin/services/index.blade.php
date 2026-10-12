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
                    <tr><td colspan="7" class="text-center text-muted py-4">No services yet.</td></tr>
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
                        <input name="category" id="svc-category" class="form-control" list="categories" required>
                        <datalist id="categories">
                            <option value="Facial"><option value="Massage"><option value="Nails"><option value="Lashes & Brows"><option value="Aesthetics"><option value="Head Spa"><option value="Home Service">
                        </datalist>
                        <div class="form-text">Must match an existing tab exactly to group with it on the public Services page — any other spelling creates a new tab.</div>
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
@endsection

@push('scripts')
<script>
    (function () {
        const variantsWrap = document.getElementById('svc-variants');
        let variantIndex = 0;

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
                document.getElementById('svc-category').value = btn.dataset.category;
                document.getElementById('svc-description').value = btn.dataset.description || '';
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
            } else {
                form.action = "{{ route('admin.services.store') }}";
                methodHolder.innerHTML = '';
                document.getElementById('service-modal-title').textContent = 'New Service';
                form.reset();
                preview.style.display = 'none';
                removeWrap.style.display = 'none';
            }
        });
    })();
</script>
@endpush
