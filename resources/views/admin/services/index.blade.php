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
                <tr><th>Name</th><th>Category</th><th>Price</th><th>Duration</th><th>Status</th><th class="text-end"></th></tr>
            </thead>
            <tbody>
                @forelse($services as $service)
                    <tr>
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
                                    data-status="{{ $service->status }}">
                                Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No services yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- CREATE/EDIT MODAL --}}
<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="serviceForm" class="modal-content">
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
                            <option value="Nails"><option value="Massage"><option value="Aesthetics"><option value="Packages">
                        </datalist>
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
    document.getElementById('serviceModal').addEventListener('show.bs.modal', (event) => {
        const btn = event.relatedTarget;
        const form = document.getElementById('serviceForm');
        const methodHolder = document.getElementById('service-method-holder');

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
        } else {
            form.action = "{{ route('admin.services.store') }}";
            methodHolder.innerHTML = '';
            document.getElementById('service-modal-title').textContent = 'New Service';
            form.reset();
        }
    });
</script>
@endpush
