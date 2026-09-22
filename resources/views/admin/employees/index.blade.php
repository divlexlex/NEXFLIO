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
                    <select name="position" class="form-select" required>
                        <option value="">Select position...</option>
                        <option value="Nail Technician">Nail Technician</option>
                        <option value="Massage Technician">Massage Technician</option>
                        <option value="Facial Technician">Facial Technician</option>
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
                        <option value="Nail Technician">Nail Technician</option>
                        <option value="Massage Technician">Massage Technician</option>
                        <option value="Facial Technician">Facial Technician</option>
                    </select>
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
@endpush
