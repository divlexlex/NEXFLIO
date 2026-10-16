@extends('layouts.admin')

@section('title', 'Employees')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Employees</h1>
    <button class="btn btn-spa" data-bs-toggle="modal" data-bs-target="#newEmployeeModal">
        <i class="bi bi-person-plus me-1"></i>Add Employee
    </button>
</div>

@if(session('newStaffCredentials'))
    @php($creds = session('newStaffCredentials'))
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-check-circle text-success me-1"></i>Employee Added Successfully</h5>
                </div>
                <div class="modal-body text-center">
                    <p class="fs-5 fw-semibold mb-1">{{ $creds['name'] }}</p>
                    <p class="text-muted mb-3">{{ $creds['position'] }}</p>
                    <div class="bg-body-tertiary rounded p-3 mb-3 text-start">
                        <div class="mb-2">
                            <div class="small text-muted">Username</div>
                            <code class="fs-6">{{ $creds['username'] }}</code>
                        </div>
                        <div>
                            <div class="small text-muted">Temporary Password</div>
                            <code class="fs-6">{{ $creds['password'] }}</code>
                        </div>
                    </div>
                    <p class="small text-muted mb-0"><strong>IMPORTANT:</strong> Save these credentials now. The temporary password will only be shown once.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <a href="{{ route('admin.employees') }}" class="btn btn-spa">Done</a>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="card p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 text-muted mb-0">Roster & Attendance</h2>
        <form method="GET" class="d-flex align-items-center gap-2">
            <label class="small text-muted">Attendance for</label>
            <input type="date" name="date" value="{{ $attendanceDate }}" class="form-control form-control-sm w-auto"
                   onchange="this.form.submit()">
        </form>
    </div>
    {{-- FILTER BAR (one GET form, auto-submit — same pattern as
         Services/Change Requests: selects submit immediately, search
         ~500ms after the last keystroke; no buttons). The date rides
         along hidden so filtering never resets the Attendance column. --}}
    <form method="GET" action="{{ route('admin.employees') }}" id="employees-filter-form" class="row g-2 align-items-end mb-2">
        <input type="hidden" name="date" value="{{ $attendanceDate }}">
        <div class="col-md-6">
            <label class="form-label small mb-0" for="filter-q">Search</label>
            <input type="search" id="filter-q" name="q" value="{{ $currentQ }}" class="form-control" placeholder="Search name or username…" autocomplete="off">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-0" for="filter-position">Position</label>
            <select id="filter-position" name="position" class="form-select">
                <option value="all" {{ $currentPosition === 'all' ? 'selected' : '' }}>All positions</option>
                @foreach($positions as $position)
                    <option value="{{ $position->name }}" {{ $currentPosition === $position->name ? 'selected' : '' }}>{{ $position->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-0" for="filter-status">Status</label>
            <select id="filter-status" name="status" class="form-select">
                <option value="all" {{ $currentStatus === 'all' ? 'selected' : '' }}>All statuses</option>
                <option value="active" {{ $currentStatus === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $currentStatus === 'inactive' ? 'selected' : '' }}>Inactive</option>
                <option value="terminated" {{ $currentStatus === 'terminated' ? 'selected' : '' }}>Terminated</option>
            </select>
        </div>
    </form>
    @if($filtersActive)
        <p class="text-muted small mb-2">Showing {{ $employees->count() }} of {{ $totalEmployees }} employees</p>
    @endif
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Position</th>
                    <th>Base Pay</th>
                    <th>Rate</th>
                    <th>Status</th>
                    <th>Break</th>
                    <th>Attendance ({{ \Carbon\Carbon::parse($attendanceDate)->format('M j') }})</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                    @php($profile = $employee->staffProfile)
                    @php($attendance = $attendances->get($employee->id))
                    <tr>
                        <td>
                            <strong>{{ $employee->fullName() }}</strong>
                            <div class="small text-muted">{{ $employee->email }}</div>
                        </td>
                        <td><code>{{ $employee->username ?? '—' }}</code></td>
                        <td>{{ $profile->position ?? '—' }}</td>
                        <td>₱{{ number_format($profile->base_pay ?? 0, 2) }}</td>
                        <td>{{ number_format($profile->commission_rate ?? 10, 1) }}%</td>
                        <td>
                            <span class="badge {{ ($profile->employment_status ?? 'active') === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ ucfirst($profile->employment_status ?? 'active') }}
                            </span>
                        </td>
                        <td>
                            @if($profile?->is_on_break)
                                <span class="badge text-bg-warning">On break</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if($attendance)
                                <span class="small">
                                    {{ $attendance->time_in->format('g:i A') }}
                                    – {{ $attendance->time_out?->format('g:i A') ?? 'now' }}
                                </span>
                            @else
                                <span class="text-muted small">No time-in</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal" data-bs-target="#editEmployeeModal"
                                    data-id="{{ $employee->id }}"
                                    data-name="{{ $employee->fullName() }}"
                                    data-base="{{ $profile->base_pay ?? 0 }}"
                                    data-rate="{{ $profile->commission_rate ?? 10 }}"
                                    data-position="{{ $profile->position }}"
                                    data-status="{{ $profile->employment_status ?? 'active' }}">
                                Edit
                            </button>
                            @if(auth()->user()->role_id === \App\Models\User::ROLE_SUPER_ADMIN)
                                <button class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal" data-bs-target="#deleteEmployeeModal"
                                        data-id="{{ $employee->id }}"
                                        data-name="{{ $employee->fullName() }}">
                                    Delete
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No staff yet — add your first employee.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- NEW EMPLOYEE MODAL --}}
<div class="modal fade" id="newEmployeeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.employees.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Add Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input name="last_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input name="first_name" class="form-control" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Middle Name <small class="text-muted">(Optional)</small></label>
                        <input name="middle_name" class="form-control">
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Contact Number <span class="text-danger">*</span></label>
                        <input name="contact_number" class="form-control" placeholder="09171234567" pattern="^(09\d{9}|\+639\d{9})$" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Position <span class="text-danger">*</span></label>
                    <select name="position" id="new-emp-position" class="form-select" required>
                        <option value="">Select position...</option>
                        @foreach($positions as $position)
                            <option value="{{ $position->name }}">{{ $position->name }}</option>
                        @endforeach
                        <option value="__new__">+ New position…</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Hired at <span class="text-danger">*</span></label>
                    <input type="date" name="hired_at" class="form-control" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Monthly Base Pay (₱) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="base_pay" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Commission Rate (%) <span class="text-danger">*</span></label>
                        <input type="number" step="0.1" name="commission_rate" class="form-control" min="0" max="100" value="10" required>
                    </div>
                </div>
                <div class="form-text">A username and temporary password are generated automatically — you'll see them once, right after saving.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa">Add Employee</button>
            </div>
        </form>
    </div>
</div>

{{-- EDIT EMPLOYEE MODAL --}}
<div class="modal fade" id="editEmployeeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="editEmployeeForm" class="modal-content">
            @csrf @method('PATCH')
            <div class="modal-header">
                <h5 class="modal-title">Edit — <span id="edit-emp-name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Monthly base pay (₱)</label>
                        <input type="number" step="0.01" name="base_pay" id="edit-emp-base" class="form-control" min="0" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Commission rate (%)</label>
                        <input type="number" step="0.1" name="commission_rate" id="edit-emp-rate" class="form-control" min="0" max="100" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Position</label>
                    <select name="position" id="edit-emp-position" class="form-select" required>
                        @foreach($positions as $position)
                            <option value="{{ $position->name }}">{{ $position->name }}</option>
                        @endforeach
                        <option value="__new__">+ New position…</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Employment status</label>
                    <select name="employment_status" id="edit-emp-status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive (removed from booking pool, record kept)</option>
                        <option value="terminated">Terminated (employment ended — cannot sign in, record kept)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- NEW POSITION MODAL (shared by Add + Edit Employee): creates the
     position without losing the employee form's typed data — the form
     below submits via fetch (JSON) and the new option is appended +
     selected in place. Without fetch (very old browser) the plain POST
     runs instead and the endpoint redirects back with a flash. --}}
<div class="modal fade" id="newPositionModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.positions.store') }}" id="newPositionForm" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">New position</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-danger mb-2 d-none" id="new-position-error"></p>
                <div class="mb-2">
                    <label class="form-label" for="new-position-name">Position name <span class="text-danger">*</span></label>
                    <input name="name" id="new-position-name" class="form-control" maxlength="100" required>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="new-position-category">Handles service category</label>
                    <select name="service_category" id="new-position-category" class="form-select">
                        <option value="">None</option>
                        @foreach(\App\Support\ServiceCatalog::CATEGORY_ORDER as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa">Add Position</button>
            </div>
        </form>
    </div>
</div>

{{-- DELETE EMPLOYEE MODAL --}}
<div class="modal fade" id="deleteEmployeeModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="deleteEmployeeForm" class="modal-content">
            @csrf @method('DELETE')
            <div class="modal-header">
                <h5 class="modal-title">Remove Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove <strong id="delete-emp-name"></strong>?</p>
                <p class="small text-muted mb-0">This will soft-delete the account. Their existing appointments and records will be preserved.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger">Delete Employee</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('editEmployeeModal').addEventListener('show.bs.modal', (event) => {
        const btn = event.relatedTarget;
        document.getElementById('editEmployeeForm').action = "{{ url('admin/employees') }}/" + btn.dataset.id;
        document.getElementById('edit-emp-name').textContent = btn.dataset.name;
        document.getElementById('edit-emp-base').value = btn.dataset.base;
        document.getElementById('edit-emp-rate').value = btn.dataset.rate;
        document.getElementById('edit-emp-position').value = btn.dataset.position || '';
        document.getElementById('edit-emp-status').value = btn.dataset.status;
    });

    document.getElementById('deleteEmployeeModal').addEventListener('show.bs.modal', (event) => {
        const btn = event.relatedTarget;
        document.getElementById('deleteEmployeeForm').action = "{{ url('admin/employees') }}/" + btn.dataset.id;
        document.getElementById('delete-emp-name').textContent = btn.dataset.name;
    });
</script>
<script>
    // Automatic employees filter bar (same pattern as the Admin Services
    // page): selects submit immediately, the search input submits ~500ms
    // after the last keystroke. No buttons — resetting to All/empty
    // re-submits and restores the full list.
    (function () {
        const form = document.getElementById('employees-filter-form');
        if (!form) return;
        const search = document.getElementById('filter-q');
        let timer = null;

        form.querySelectorAll('#filter-position, #filter-status').forEach((sel) => {
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
    // "+ New position…" (last option of both employee selects): open the
    // shared modal instead of submitting that value, then create via fetch
    // so the employee form keeps everything already typed. The new option
    // is appended to both selects and selected in the originating one.
    (function () {
        const modal = document.getElementById('newPositionModal');
        const form = document.getElementById('newPositionForm');
        const errorEl = document.getElementById('new-position-error');
        const selects = ['new-emp-position', 'edit-emp-position']
            .map((id) => document.getElementById(id))
            .filter(Boolean);
        let target = null;

        selects.forEach((select) => {
            select.addEventListener('change', () => {
                if (select.value !== '__new__') return;
                target = select;
                select.value = '';
                errorEl.textContent = '';
                errorEl.classList.add('d-none');
                form.reset();
                new bootstrap.Modal(modal).show();
            });
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
                            || (data.errors && (data.errors.service_category || [])[0])
                            || 'Could not add this position.';
                        throw new Error(message);
                    }
                    return data;
                })
                .then((position) => {
                    selects.forEach((select) => {
                        const existing = Array.from(select.options).find((o) => o.value === position.name);
                        if (existing) {
                            existing.selected = select === target;
                            return;
                        }
                        const option = document.createElement('option');
                        option.value = position.name;
                        option.textContent = position.name;
                        const plus = select.querySelector('option[value="__new__"]');
                        select.insertBefore(option, plus);
                        if (select === target) select.value = position.name;
                    });
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
