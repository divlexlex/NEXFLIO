@extends('layouts.admin')

@section('title', 'Inventory')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Inventory</h1>
    <button class="btn btn-spa" data-bs-toggle="modal" data-bs-target="#newItemModal">
        <i class="bi bi-plus-lg me-1"></i>New Item
    </button>
</div>

<div class="card p-3 mb-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>On Hand</th>
                    <th>Reorder Point</th>
                    <th>FIFO Batches (oldest first)</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr class="{{ $item->quantity <= $item->reorder_point ? 'table-warning' : '' }}">
                        <td>
                            <strong>{{ $item->item_name }}</strong>
                            <div class="small text-muted">₱{{ number_format($item->price_per_unit, 2) }} / {{ $item->unit }}</div>
                        </td>
                        <td>
                            <span class="fs-5 fw-bold">{{ $item->quantity }}</span>
                            <small class="text-muted">{{ $item->unit }}(s)</small>
                            @if($item->quantity <= $item->reorder_point)
                                <span class="badge text-bg-warning ms-1">Low</span>
                            @endif
                        </td>
                        <td>{{ $item->reorder_point }}</td>
                        <td>
                            @forelse($item->batches as $batch)
                                <span class="badge text-bg-light border me-1" title="Received {{ $batch->received_at->format('M j, Y') }}">
                                    {{ $batch->quantity_remaining }} @ ₱{{ number_format($batch->unit_cost, 2) }}
                                </span>
                            @empty
                                <span class="text-muted small">No stock</span>
                            @endforelse
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-success"
                                    data-bs-toggle="modal" data-bs-target="#receiveModal"
                                    data-id="{{ $item->id }}" data-name="{{ $item->item_name }}"
                                    data-cost="{{ $item->price_per_unit }}">
                                <i class="bi bi-box-arrow-in-down"></i> Receive
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal" data-bs-target="#pullOutModal"
                                    data-id="{{ $item->id }}" data-name="{{ $item->item_name }}">
                                <i class="bi bi-box-arrow-up"></i> Pull Out
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No inventory items yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card p-3">
    <h2 class="h6 text-muted mb-3">Recent Stock Movements (append-only ledger)</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr><th>When</th><th>Item</th><th>Type</th><th>Qty</th><th>By</th><th>Reason</th></tr>
            </thead>
            <tbody>
                @forelse($recentMovements as $movement)
                    <tr>
                        <td class="text-muted small">{{ $movement->created_at->format('M j, g:i A') }}</td>
                        <td>{{ $movement->inventory->item_name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $movement->type === 'in' ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ str_replace('_', ' ', $movement->type) }}
                            </span>
                        </td>
                        <td>{{ $movement->quantity }}</td>
                        <td>{{ $movement->user->name ?? 'System' }}</td>
                        <td class="text-muted small">{{ $movement->reason ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No movements yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- NEW ITEM MODAL --}}
<div class="modal fade" id="newItemModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.inventory.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">New Inventory Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label">Item name</label>
                    <input name="item_name" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Unit</label>
                        <input name="unit" class="form-control" placeholder="bottle" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Reorder point</label>
                        <input type="number" name="reorder_point" class="form-control" min="0" value="3" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Price per unit (₱)</label>
                        <input type="number" step="0.01" name="price_per_unit" class="form-control" min="0" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Opening stock (whole units)</label>
                        <input type="number" name="initial_quantity" class="form-control" min="1">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa">Add Item</button>
            </div>
        </form>
    </div>
</div>

{{-- RECEIVE MODAL --}}
<div class="modal fade" id="receiveModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="receiveForm" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Receive Stock — <span id="receive-name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Quantity (whole units)</label>
                        <input type="number" name="quantity" class="form-control" min="1" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Unit cost (₱)</label>
                        <input type="number" step="0.01" name="unit_cost" id="receive-cost" class="form-control" min="0" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Note (optional)</label>
                    <input name="reason" class="form-control" placeholder="e.g. Supplier delivery">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success">Receive</button>
            </div>
        </form>
    </div>
</div>

{{-- PULL OUT MODAL --}}
<div class="modal fade" id="pullOutModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="pullOutForm" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Pull Out — <span id="pullout-name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Deducted FIFO from the oldest batch first. Whole units only.</p>
                <div class="mb-2">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" class="form-control" min="1" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Reason (required)</label>
                    <input name="reason" class="form-control" placeholder="e.g. Damaged, expired, transferred" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger">Pull Out</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('receiveModal').addEventListener('show.bs.modal', (event) => {
        const btn = event.relatedTarget;
        document.getElementById('receiveForm').action = "{{ url('admin/inventory') }}/" + btn.dataset.id + "/batches";
        document.getElementById('receive-name').textContent = btn.dataset.name;
        document.getElementById('receive-cost').value = btn.dataset.cost;
    });
    document.getElementById('pullOutModal').addEventListener('show.bs.modal', (event) => {
        const btn = event.relatedTarget;
        document.getElementById('pullOutForm').action = "{{ url('admin/inventory') }}/" + btn.dataset.id + "/pull-out";
        document.getElementById('pullout-name').textContent = btn.dataset.name;
    });
</script>
@endpush
