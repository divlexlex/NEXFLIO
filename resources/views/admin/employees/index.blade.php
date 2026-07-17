@extends('layouts.admin')

@section('title', 'Employees')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Employees</h1>
    <button class="btn btn-spa" data-bs-toggle="modal" data-bs-target="#newEmployeeModal">
        <i class="bi bi-person-plus me-1"></i>Add Employee
    </button>
</div>

<div class="card p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 text-muted mb-0">Roster & Attendance</h2>
        <form method="GET" class="d-flex align-items-center gap-2">
            <label class="small text-muted">Attendance for</label>
            <input type="date" name="date" value="{{ $attendanceDate }}" class="form-control form-control-sm w-auto"
                   onchange="this.form.submit()">
        </form>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Name</th>
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
                            <strong>{{ $employee->name }}</strong>
                            <div class="small text-muted">{{ $employee->email }}</div>
                        </td>
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
                                    data-name="{{ $employee->name }}"
                                    data-base="{{ $profile->base_pay ?? 0 }}"
                                    data-rate="{{ $profile->commission_rate ?? 10 }}"
                                    data-position="{{ $profile->position }}"
                                    data-status="{{ $profile->employment_status ?? 'active' }}">
                                Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No staff yet — add your first employee.</td></tr>
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
                <div class="mb-2">
                    <label class="form-label">Full name</label>
                    <input name="name" class="form-control" required>
                </div>
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Temporary password</label>
                        <input name="password" class="form-control" minlength="8" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Position</label>
                        <input name="position" class="form-control" placeholder="Nail Technician">
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Hired at</label>
                        <input type="date" name="hired_at" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Monthly base pay (₱)</label>
                        <input type="number" step="0.01" name="base_pay" class="form-control" min="0" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Commission rate (%)</label>
                        <input type="number" step="0.1" name="commission_rate" class="form-control" min="0" max="100" value="10">
                    </div>
                </div>
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
                    <input name="position" id="edit-emp-position" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label">Employment status</label>
                    <select name="employment_status" id="edit-emp-status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive (removed from booking pool, record kept)</option>
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
</script>
@endpush
