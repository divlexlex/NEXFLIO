@extends('layouts.admin')

@section('title', 'Inventory')

@push('head')
<style>
    /* INVENTORY COLORWAY (colors only — the app font stack is untouched).
       Low-stock rows follow the admin theme instead of Bootstrap's
       hardcoded-cream warning row styling, which stays near-white in dark
       mode while every other surface goes dark. */
    .nx-inv-table { background-color: #FAF7F0; }
    html[data-bs-theme="dark"] .nx-inv-table { background-color: var(--nex-surface); }

    .nx-inv-table > thead > tr > th {
        background-color: var(--nex-table-header-bg);
        color: #8F6236;
        text-transform: uppercase;
        letter-spacing: .06em;
        border-color: rgba(201, 162, 75, .14);
    }
    html[data-bs-theme="dark"] .nx-inv-table > thead > tr > th {
        background-color: var(--nex-raised);
        color: #C6A15B;
    }

    .nx-inv-table > tbody > tr > * { border-color: rgba(201, 162, 75, .14); }

    .nx-inv-name { color: var(--nex-text); }
    html[data-bs-theme="dark"] .nx-inv-name { color: #F3E9D7; }

    .nx-inv-price { color: #8F6236; }
    html[data-bs-theme="dark"] .nx-inv-price { color: #A68B6B; }

    .nx-inv-num { color: var(--nex-text); }
    html[data-bs-theme="dark"] .nx-inv-num { color: #F3E9D7; }

    /* Soft amber pill: transparent amber wash + amber text + thin amber
       border — never a solid yellow slab. */
    .nx-inv-low {
        background-color: rgba(242, 194, 48, .16);
        color: #7A5600;
        border: 1px solid rgba(190, 150, 30, .55);
    }
    html[data-bs-theme="dark"] .nx-inv-low {
        background-color: rgba(242, 194, 48, .13);
        color: #F2C230;
        border-color: rgba(242, 194, 48, .45);
    }

    .nx-inv-nostock { color: #A68B6B; font-style: italic; }

    /* Action buttons: unified outline size/style, color-coded. */
    .nx-inv-btn-receive {
        --bs-btn-color: #8F6236; --bs-btn-border-color: #8F6236;
        --bs-btn-hover-color: #fff; --bs-btn-hover-bg: #8F6236; --bs-btn-hover-border-color: #8F6236;
        --bs-btn-active-color: #fff; --bs-btn-active-bg: #8F6236; --bs-btn-active-border-color: #8F6236;
        --bs-btn-disabled-color: #8F6236; --bs-btn-disabled-border-color: #8F6236;
    }
    html[data-bs-theme="dark"] .nx-inv-btn-receive {
        --bs-btn-color: #C9A24B; --bs-btn-border-color: #C9A24B;
        --bs-btn-hover-color: #241811; --bs-btn-hover-bg: #C9A24B; --bs-btn-hover-border-color: #C9A24B;
        --bs-btn-active-color: #241811; --bs-btn-active-bg: #C9A24B; --bs-btn-active-border-color: #C9A24B;
        --bs-btn-disabled-color: #C9A24B; --bs-btn-disabled-border-color: #C9A24B;
    }
    .nx-inv-btn-pull {
        --bs-btn-color: #A6564B; --bs-btn-border-color: #A6564B;
        --bs-btn-hover-color: #fff; --bs-btn-hover-bg: #A6564B; --bs-btn-hover-border-color: #A6564B;
        --bs-btn-active-color: #fff; --bs-btn-active-bg: #A6564B; --bs-btn-active-border-color: #A6564B;
        --bs-btn-disabled-color: #A6564B; --bs-btn-disabled-border-color: #A6564B;
    }
    html[data-bs-theme="dark"] .nx-inv-btn-pull {
        --bs-btn-color: #CF8B80; --bs-btn-border-color: #CF8B80;
        --bs-btn-hover-color: #241811; --bs-btn-hover-bg: #CF8B80; --bs-btn-hover-border-color: #CF8B80;
        --bs-btn-active-color: #241811; --bs-btn-active-bg: #CF8B80; --bs-btn-active-border-color: #CF8B80;
        --bs-btn-disabled-color: #CF8B80; --bs-btn-disabled-border-color: #CF8B80;
    }
    .nx-inv-btn-edit {
        --bs-btn-color: #71604F; --bs-btn-border-color: #71604F;
        --bs-btn-hover-color: #fff; --bs-btn-hover-bg: #71604F; --bs-btn-hover-border-color: #71604F;
        --bs-btn-active-color: #fff; --bs-btn-active-bg: #71604F; --bs-btn-active-border-color: #71604F;
        --bs-btn-disabled-color: #71604F; --bs-btn-disabled-border-color: #71604F;
    }
    html[data-bs-theme="dark"] .nx-inv-btn-edit {
        --bs-btn-color: #A68B6B; --bs-btn-border-color: #A68B6B;
        --bs-btn-hover-color: #241811; --bs-btn-hover-bg: #A68B6B; --bs-btn-hover-border-color: #A68B6B;
        --bs-btn-active-color: #241811; --bs-btn-active-bg: #A68B6B; --bs-btn-active-border-color: #A68B6B;
        --bs-btn-disabled-color: #A68B6B; --bs-btn-disabled-border-color: #A68B6B;
    }

    /* Filter bar labels + pager accents. */
    .nx-inv-filter-label { color: #8F6236; text-transform: uppercase; letter-spacing: .06em; }
    html[data-bs-theme="dark"] .nx-inv-filter-label { color: #C6A15B; }

    .nx-inv-pager .page-link { border-color: rgba(201, 162, 75, .25); }
    html[data-bs-theme="dark"] .nx-inv-pager .page-item.active .page-link {
        background-color: #C9A24B; border-color: #C9A24B; color: #241811;
    }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Inventory</h1>
    <button class="btn btn-spa" data-bs-toggle="modal" data-bs-target="#newItemModal">
        <i class="bi bi-plus-lg me-1"></i>New Item
    </button>
</div>

{{-- FILTER BAR (one GET form, auto-submit — same pattern as
     Services/Change Requests: selects submit immediately, search ~500ms
     after the last keystroke; no buttons) --}}
<form method="GET" action="{{ route('admin.inventory') }}" id="inventory-filter-form" class="mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small mb-0 nx-inv-filter-label" for="filter-category">Category</label>
            @include('partials.inventory-category-select', ['current' => $category, 'categories' => $categories, 'form' => true, 'id' => 'filter-category'])
        </div>
        <div class="col-md-4">
            <label class="form-label small mb-0 nx-inv-filter-label" for="filter-q">Search</label>
            <input type="search" id="filter-q" name="q" value="{{ $currentQ }}" class="form-control" placeholder="Search item name…" autocomplete="off">
        </div>
        <div class="col-md-4">
            <label class="form-label small mb-0 nx-inv-filter-label" for="filter-stock">Stock status</label>
            <select id="filter-stock" name="stock" class="form-select">
                <option value="all" {{ $currentStock === 'all' ? 'selected' : '' }}>All items</option>
                <option value="in" {{ $currentStock === 'in' ? 'selected' : '' }}>In stock</option>
                <option value="low" {{ $currentStock === 'low' ? 'selected' : '' }}>Low stock</option>
                <option value="out" {{ $currentStock === 'out' ? 'selected' : '' }}>Out of stock</option>
            </select>
        </div>
    </div>
</form>
<div class="mb-3 d-flex justify-content-end align-items-center flex-wrap gap-2">
    @if($currentQ !== '')
        <span class="small text-muted">Showing {{ $items->total() }} result(s) for "{{ $currentQ }}" across all categories</span>
    @elseif($filtersActive)
        <span class="small text-muted">Showing {{ $items->total() }} of {{ $totalInCategory }} items</span>
    @else
        <span class="small text-muted">{{ $totalInCategory }} item(s) in this category</span>
    @endif
</div>

<div class="card p-3 mb-4">
    <div class="table-responsive">
        <table class="table align-middle nx-inv-table">
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
                    <tr>
                        <td>
                            <strong class="nx-inv-name">{{ $item->item_name }}</strong>
                            <div class="small nx-inv-price">₱{{ number_format($item->price_per_unit, 2) }} / {{ $item->unit }}</div>
                        </td>
                        <td>
                            <span class="fs-5 fw-bold nx-inv-num">{{ $item->quantity }}</span>
                            <small class="nx-inv-price">{{ $item->unit }}(s)</small>
                            @if($item->quantity <= $item->reorder_point)
                                <span class="badge nx-inv-low ms-1">Low</span>
                            @endif
                        </td>
                        <td class="nx-inv-num">{{ $item->reorder_point }}</td>
                        <td>
                            @forelse($item->batches as $batch)
                                <span class="badge text-bg-light border me-1" title="Received {{ $batch->received_at->format('M j, Y') }}">
                                    {{ $batch->quantity_remaining }} @ ₱{{ number_format($batch->unit_cost, 2) }}
                                </span>
                            @empty
                                <span class="small nx-inv-nostock">No stock</span>
                            @endforelse
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm nx-inv-btn nx-inv-btn-receive"
                                    data-bs-toggle="modal" data-bs-target="#receiveModal"
                                    data-id="{{ $item->id }}" data-name="{{ $item->item_name }}"
                                    data-cost="{{ $item->price_per_unit }}">
                                <i class="bi bi-box-arrow-in-down"></i> Receive
                            </button>
                            <button class="btn btn-sm nx-inv-btn nx-inv-btn-pull"
                                    data-bs-toggle="modal" data-bs-target="#pullOutModal"
                                    data-id="{{ $item->id }}" data-name="{{ $item->item_name }}">
                                <i class="bi bi-box-arrow-up"></i> Pull Out
                            </button>
                            <button class="btn btn-sm nx-inv-btn nx-inv-btn-edit"
                                    data-bs-toggle="modal" data-bs-target="#editItemModal"
                                    data-id="{{ $item->id }}" data-name="{{ $item->item_name }}"
                                    data-category="{{ collect($categories)->first(fn ($n) => mb_strtolower($n) === mb_strtolower((string) $item->category)) ?? $item->category }}" data-unit="{{ $item->unit }}"
                                    data-price="{{ $item->price_per_unit }}" data-reorder="{{ $item->reorder_point }}">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No inventory items yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="nx-inv-pager">{{ $items->links() }}</div>
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
                <div class="mb-2">
                    <label class="form-label">Category</label>
                    <select name="category" id="new-item-category" class="form-select" required>
                        <option value="">Select category</option>
                        @foreach($categories as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                        <option value="__new__">+ New category…</option>
                    </select>
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

{{-- EDIT ITEM MODAL (master data only — name, category, unit, price,
     reorder point; deliberately no quantity/batch fields: stock moves
     only via Receive / Pull Out) --}}
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="editItemForm" class="modal-content">
            @csrf @method('PATCH')
            <div class="modal-header">
                <h5 class="modal-title">Edit Item — <span id="edit-item-title"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="form-label" for="edit-item-name">Item name</label>
                    <input name="item_name" id="edit-item-name" class="form-control" maxlength="255" required>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="edit-item-category">Category</label>
                    <select name="category" id="edit-item-category" class="form-select" required>
                        <option value="">Select category</option>
                        @foreach($categories as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                        <option value="__new__">+ New category…</option>
                    </select>
                </div>
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label" for="edit-item-unit">Unit</label>
                        <input name="unit" id="edit-item-unit" class="form-control" maxlength="50" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label" for="edit-item-reorder">Reorder point</label>
                        <input type="number" name="reorder_point" id="edit-item-reorder" class="form-control" min="0" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="edit-item-price">Price per unit (₱)</label>
                    <input type="number" step="0.01" name="price_per_unit" id="edit-item-price" class="form-control" min="0" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa">Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- NEW CATEGORY MODAL (shared by New + Edit Item): creates the category
     via fetch so the item form keeps its typed data; without fetch the
     plain POST runs and the endpoint redirects back with a flash. --}}
<div class="modal fade" id="newCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.inventory-categories.store') }}" id="newCategoryForm" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">New category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-danger mb-2 d-none" id="new-category-error"></p>
                <div class="mb-2">
                    <label class="form-label" for="new-category-name">Category name <span class="text-danger">*</span></label>
                    <input name="name" id="new-category-name" class="form-control" maxlength="255" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa">Add Category</button>
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
    // Automatic inventory filter bar (same pattern as the Admin Services
    // page): selects submit immediately, the search input submits ~500ms
    // after the last keystroke. No buttons — resetting to All/empty
    // re-submits and restores the full list.
    (function () {
        const form = document.getElementById('inventory-filter-form');
        if (!form) return;
        const search = document.getElementById('filter-q');
        let timer = null;

        form.querySelectorAll('#filter-category, #filter-stock').forEach((sel) => {
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
    document.getElementById('editItemModal').addEventListener('show.bs.modal', (event) => {
        const btn = event.relatedTarget;
        document.getElementById('editItemForm').action = "{{ url('admin/inventory') }}/" + btn.dataset.id;
        document.getElementById('edit-item-title').textContent = btn.dataset.name;
        document.getElementById('edit-item-name').value = btn.dataset.name;
        // Match the item's category case-insensitively: legacy lowercase
        // rows ('massage') must still land on their option ('Massage')
        // instead of a blank select. No match leaves the placeholder, and
        // the required rule + server validation refuse an empty save.
        const catSelect = document.getElementById('edit-item-category');
        const current = (btn.dataset.category || '').toLowerCase();
        const match = Array.from(catSelect.options).find((o) => o.value.toLowerCase() === current);
        catSelect.value = match ? match.value : '';
        document.getElementById('edit-item-unit').value = btn.dataset.unit || '';
        document.getElementById('edit-item-reorder').value = btn.dataset.reorder ?? '';
        document.getElementById('edit-item-price').value = btn.dataset.price ?? '';
    });

    // "+ New category…" (last option of both item selects): open the shared
    // modal instead of submitting that value, then create via fetch so the
    // item form keeps everything already typed. The new option is appended
    // to both selects and selected in the originating one.
    (function () {
        const modal = document.getElementById('newCategoryModal');
        const form = document.getElementById('newCategoryForm');
        const errorEl = document.getElementById('new-category-error');
        const selects = ['new-item-category', 'edit-item-category']
            .map((id) => document.getElementById(id))
            .filter(Boolean);
        let target = null;
        let restoreValue = '';
        let justCreated = false;

        selects.forEach((select) => {
            // Remember the pre-change value so cancelling out of the new
            // category modal restores the item's original category.
            select.addEventListener('focus', () => {
                select.dataset.prev = select.value;
            });
            select.addEventListener('change', () => {
                if (select.value !== '__new__') return;
                target = select;
                restoreValue = select.dataset.prev || '';
                justCreated = false;
                select.value = '';
                errorEl.textContent = '';
                errorEl.classList.add('d-none');
                form.reset();
                new bootstrap.Modal(modal).show();
            });
        });

        modal.addEventListener('hidden.bs.modal', () => {
            // Dismissed without creating: put the original selection back.
            if (!justCreated && target) {
                target.value = restoreValue;
            }
            target = null;
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
                    selects.forEach((select) => {
                        const existing = Array.from(select.options).find((o) => o.value === category.name);
                        if (existing) {
                            existing.selected = select === target;
                            return;
                        }
                        const option = document.createElement('option');
                        option.value = category.name;
                        option.textContent = category.name;
                        const plus = select.querySelector('option[value="__new__"]');
                        select.insertBefore(option, plus);
                        if (select === target) select.value = category.name;
                    });
                    justCreated = true;
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
