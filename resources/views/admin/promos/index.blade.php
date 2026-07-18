@extends('layouts.admin')

@section('title', 'Promos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Promos</h1>
    <button class="btn btn-spa" data-bs-toggle="modal" data-bs-target="#promoModal" data-mode="create">
        <i class="bi bi-plus-lg me-1"></i>New Promo
    </button>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr><th style="width:60px;"></th><th>Title</th><th>Service</th><th>Price</th><th>Status</th><th class="text-end"></th></tr>
            </thead>
            <tbody>
                @forelse($promos as $promo)
                    <tr>
                        <td>@include('admin.partials.thumb', ['url' => $promo->image_url])</td>
                        <td><strong>{{ $promo->title }}</strong></td>
                        <td>{{ $promo->service->name ?? '—' }}</td>
                        <td>₱{{ number_format($promo->price, 2) }}</td>
                        <td>
                            <span class="badge {{ $promo->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $promo->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal" data-bs-target="#promoModal"
                                    data-mode="edit"
                                    data-id="{{ $promo->id }}"
                                    data-title="{{ $promo->title }}"
                                    data-price="{{ $promo->price }}"
                                    data-service="{{ $promo->service_id }}"
                                    data-active="{{ $promo->is_active ? 1 : 0 }}"
                                    data-image="{{ $promo->image_url }}">
                                Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No promos yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- CREATE/EDIT MODAL --}}
<div class="modal fade" id="promoModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="promoForm" class="modal-content" enctype="multipart/form-data">
            @csrf
            <span id="promo-method-holder"></span>
            <div class="modal-header">
                <h5 class="modal-title" id="promo-modal-title">New Promo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Title</label>
                    <input name="title" id="promo-title" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Price (₱)</label>
                        <input type="number" step="0.01" name="price" id="promo-price" class="form-control" min="0" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Linked service (optional)</label>
                        <select name="service_id" id="promo-service" class="form-select">
                            <option value="">— None —</option>
                            @foreach($services as $service)
                                <option value="{{ $service->id }}">{{ $service->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-check mb-2">
                    <input type="checkbox" name="is_active" id="promo-active" class="form-check-input" value="1" checked>
                    <label class="form-check-label" for="promo-active">Active (shown to clients)</label>
                </div>
                @include('admin.partials.image-field', ['prefix' => 'promo'])
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
    document.getElementById('promoModal').addEventListener('show.bs.modal', (event) => {
        const btn = event.relatedTarget;
        const form = document.getElementById('promoForm');
        const methodHolder = document.getElementById('promo-method-holder');

        const preview = document.getElementById('promo-image-preview');
        const removeWrap = document.getElementById('promo-remove-wrap');
        const removeBox = document.getElementById('promo-remove-image');

        if (btn.dataset.mode === 'edit') {
            form.action = "{{ url('admin/promos') }}/" + btn.dataset.id;
            methodHolder.innerHTML = '<input type="hidden" name="_method" value="PATCH">';
            document.getElementById('promo-modal-title').textContent = 'Edit Promo';
            document.getElementById('promo-title').value = btn.dataset.title;
            document.getElementById('promo-price').value = btn.dataset.price;
            document.getElementById('promo-service').value = btn.dataset.service || '';
            document.getElementById('promo-active').checked = btn.dataset.active === '1';
            document.getElementById('promo-image').value = '';
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
            form.action = "{{ route('admin.promos.store') }}";
            methodHolder.innerHTML = '';
            document.getElementById('promo-modal-title').textContent = 'New Promo';
            form.reset();
            document.getElementById('promo-active').checked = true;
            preview.style.display = 'none';
            removeWrap.style.display = 'none';
        }
    });
</script>
@endpush
