@extends('layouts.staff')

@section('title', 'Dashboard')

@section('content')
@php($profile = auth()->user()->staffProfile)

<div class="mb-4">
    <h1 class="h3 mb-1">Hi, {{ auth()->user()->first_name ?? explode(' ', auth()->user()->name)[0] }}</h1>
    <p class="text-muted mb-0">
        {{ $profile?->position ?? 'Staff' }}
        @if($profile)
            <span class="badge {{ $profile->employment_status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }} ms-1">
                {{ ucfirst($profile->employment_status) }}
            </span>
        @endif
    </p>
</div>

{{-- Refreshable region: all data cards (attendance, pay, appointments,
     commissions, My Leaves, leave requests). Swapped in place by
     nx-refresh.js (no page reload). The Request Leave modal below stays
     outside on purpose, so its conflict-check listeners survive a refresh. --}}
<div id="nx-refresh-region">
<div class="row g-3 mb-3">
    {{-- ATTENDANCE --}}
    <div class="col-md-6">
        <div class="card p-3 h-100">
            <h2 class="h6 text-uppercase text-muted mb-3">Today's Attendance</h2>
            @if(!$todayAttendance)
                <p class="mb-3">You haven't timed in yet today.</p>
                <form method="POST" action="{{ route('staff.attendance.time-in') }}">
                    @csrf
                    <button class="btn btn-spa"><i class="bi bi-box-arrow-in-right me-1"></i>Time In</button>
                </form>
            @elseif(!$todayAttendance->time_out)
                <p class="mb-2">
                    Timed in at <strong>{{ $todayAttendance->time_in->format('g:i A') }}</strong>.
                    @if($todayAttendance->isLate())
                        <span class="badge text-bg-warning">Late {{ $todayAttendance->lateMinutes() }}m</span>
                    @endif
                </p>
                <p class="mb-3">
                    @if($profile?->is_on_break)
                        <span class="badge text-bg-warning">On break since {{ $profile->break_started_at?->format('g:i A') }}</span>
                    @else
                        <span class="badge text-bg-success">Active</span>
                    @endif
                </p>
                <div class="d-flex gap-2 mb-3">
                    <form method="POST" action="{{ route('staff.attendance.break') }}">
                        @csrf
                        <button class="btn btn-outline-secondary">
                            @if($profile?->is_on_break)
                                <i class="bi bi-play-fill me-1"></i>End Break
                            @else
                                <i class="bi bi-pause-fill me-1"></i>Start Break
                            @endif
                        </button>
                    </form>
                    <form method="POST" action="{{ route('staff.attendance.time-out') }}">
                        @csrf
                        <button class="btn btn-outline-secondary"><i class="bi bi-box-arrow-right me-1"></i>Time Out</button>
                    </form>
                </div>
                <p class="text-muted small mb-0">Total so far: {{ $todayAttendance->workedForHumans() }}</p>
            @else
                <p class="mb-2">
                    Timed in at <strong>{{ $todayAttendance->time_in->format('g:i A') }}</strong>@if($todayAttendance->isLate()) <span class="badge text-bg-warning">Late {{ $todayAttendance->lateMinutes() }}m</span>@endif,
                    timed out at <strong>{{ $todayAttendance->time_out->format('g:i A') }}</strong>.
                </p>
                <p class="text-muted small mb-0">Shift complete — see you next time. Total today: {{ $todayAttendance->workedForHumans() }}.</p>
            @endif
        </div>
    </div>

    {{-- EARNINGS --}}
    <div class="col-md-6">
        <div class="card p-3 h-100">
            <h2 class="h6 text-uppercase text-muted mb-3">This Week's Pay</h2>
            <p class="display-6 mb-0">₱{{ number_format($weeklyPay['total'], 2) }}</p>
            <p class="text-muted small mb-0">Daily ₱{{ number_format($weeklyPay['daily_base_pay'], 2) }} × {{ $weeklyPay['days_present'] }} day(s) attended = ₱{{ number_format($weeklyPay['base_total'], 2) }} + ₱{{ number_format($weeklyPay['commission'], 2) }} commission · {{ \Carbon\Carbon::parse($weeklyPay['week_start'])->format('M j') }} – {{ \Carbon\Carbon::parse($weeklyPay['week_end'])->format('M j, Y') }}</p>
        </div>
    </div>
</div>

{{-- TODAY'S APPOINTMENTS --}}
<div class="card p-3 mb-3">
    <h2 class="h6 text-uppercase text-muted mb-3">Today's Appointments</h2>
    @if($todaysAppointments->isEmpty())
        <p class="text-muted mb-0">Nothing assigned to you today.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Time</th><th>Client</th><th>Service</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach($todaysAppointments as $appt)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($appt->start_time)->format('g:i A') }}</td>
                            <td>{{ $appt->clientName() ?? '—' }}</td>
                            <td>{{ $appt->service->name ?? '—' }}</td>
                            <td><span class="badge text-bg-light text-dark">{{ $appt->status->label() }}</span></td>
                            <td>
                                @if($appt->status->value === 'in-service')
                                    <form method="POST" action="{{ route('staff.appointments.complete', $appt->id) }}" class="d-inline"
                                          onsubmit="return confirm('Mark this service as completed?')">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-sm btn-spa">Finish Service</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- UPCOMING APPOINTMENTS --}}
<div class="card p-3 mb-3">
    <h2 class="h6 text-uppercase text-muted mb-3">Upcoming Appointments</h2>
    @if($upcomingAppointments->isEmpty())
        <p class="text-muted mb-0">Nothing else assigned to you yet.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Date</th><th>Time</th><th>Client</th><th>Service</th></tr></thead>
                <tbody>
                    @foreach($upcomingAppointments as $appt)
                        <tr>
                            <td>{{ $appt->appointment_date->format('M j, Y') }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($appt->start_time)->format('g:i A') }}</td>
                            <td>{{ $appt->clientName() ?? '—' }}</td>
                            <td>{{ $appt->service->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="row g-3 mb-3">
    {{-- RECENT COMMISSIONS --}}
    <div class="col-lg-4">
        <div class="card p-3 h-100">
            <h2 class="h6 text-uppercase text-muted mb-3">Recent Commissions</h2>
            @if($recentCommissions->isEmpty())
                <p class="text-muted mb-0">No commissions earned yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Date</th><th>Service</th><th class="text-end">Amount</th></tr></thead>
                        <tbody>
                            @foreach($recentCommissions as $commission)
                                <tr>
                                    <td>{{ $commission->earned_at->format('M j, Y') }}</td>
                                    <td>{{ $commission->appointment?->service?->name ?? '—' }}</td>
                                    <td class="text-end">₱{{ number_format($commission->amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- MY LEAVES (own balances only — derived from the logged-in user) --}}
    <div class="col-lg-4">
        <div class="card p-3 h-100">
            <h2 class="h6 text-uppercase text-muted mb-3">My Leaves — {{ $leaveYear }}</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-2">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th class="text-end">Consumed</th>
                            <th class="text-end">Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Sick Leave</td>
                            <td class="text-end">{{ $myLeaveBalances['sl_used'] }}</td>
                            <td class="text-end">{{ $myLeaveBalances['sl_remaining'] }}</td>
                        </tr>
                        <tr>
                            <td>Vacation Leave</td>
                            <td class="text-end">{{ $myLeaveBalances['vl_used'] }}</td>
                            <td class="text-end">{{ $myLeaveBalances['vl_remaining'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mb-0">12 Sick Leave + 12 Vacation Leave per year, consumable only — not convertible to cash. Unused leaves reset every January.</p>
        </div>
    </div>

    {{-- LEAVE REQUESTS --}}
    <div class="col-lg-4">
        <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h6 text-uppercase text-muted mb-0">Leave Requests</h2>
                <button class="btn btn-sm btn-spa" data-bs-toggle="modal" data-bs-target="#leaveModal">
                    <i class="bi bi-plus-lg me-1"></i>Request Leave
                </button>
            </div>
            @if($leaves->isEmpty())
                <p class="text-muted mb-0">No leave requests yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Dates</th><th>Type</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach($leaves as $leave)
                                <tr>
                                    <td>{{ $leave->start_date->format('M j') }}&ndash;{{ $leave->end_date->format('M j, Y') }}</td>
                                    <td>{{ ucfirst($leave->type) }}</td>
                                    <td>
                                        <span class="badge {{ match($leave->status) {
                                            'approved' => 'text-bg-success',
                                            'denied' => 'text-bg-danger',
                                            default => 'text-bg-warning',
                                        } }}">{{ ucfirst($leave->status) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
</div>

{{-- REQUEST LEAVE MODAL --}}
<div class="modal fade" id="leaveModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('staff.leaves.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Request Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- Pre-submit conflict warning: filled by the script as soon as
                     both dates are chosen. No Proceed/Change buttons here — the
                     form is still open, so Submit is the proceed action and the
                     date fields are the change action. --}}
                <div class="alert alert-warning d-none" id="leave-conflict-check">
                    <p class="fw-semibold mb-2">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <span id="leave-conflict-check-title">This leave overlaps an existing booking:</span>
                    </p>
                    <ul class="small mb-3" id="leave-conflict-check-list"></ul>
                    <p class="small text-muted mt-2 mb-0">Conflicts do not block the request — you can still file it.</p>
                </div>
                @php($leaveConflicts = session('leaveConflicts'))
                @if($leaveConflicts)
                    <div class="alert alert-warning" id="leave-conflicts">
                        <p class="fw-semibold mb-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            This leave overlaps {{ count($leaveConflicts) === 1 ? 'an existing booking' : 'existing bookings' }}:
                        </p>
                        <ul class="small mb-3">
                            @foreach($leaveConflicts as $conflict)
                                <li>
                                    <strong>{{ $conflict['date_label'] }}</strong> at {{ $conflict['time_label'] }}
                                    — {{ $conflict['service'] }} ({{ $conflict['client'] }})
                                </li>
                            @endforeach
                        </ul>
                        <div class="d-flex gap-2">
                            <button type="submit" name="confirm_conflicts" value="1" class="btn btn-sm btn-spa">
                                Proceed anyway
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                    onclick="document.getElementById('leave-conflicts').classList.add('d-none')">
                                Change dates
                            </button>
                        </div>
                        <p class="small text-muted mt-2 mb-0">Conflicts do not block the request — you can still file it.</p>
                    </div>
                @endif
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" min="{{ now()->toDateString() }}" value="{{ old('start_date') }}" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" min="{{ now()->toDateString() }}" value="{{ old('end_date') }}" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" required>
                        @foreach(\App\Models\LeaveRequest::TYPES as $type)
                            <option value="{{ $type }}" {{ old('type') === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" rows="3" required>{{ old('reason') }}</textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa">Submit</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('leaveModal');
    if (!modal) {
        return;
    }

    const form = modal.querySelector('form');
    const startInput = form.querySelector('input[name="start_date"]');
    const endInput = form.querySelector('input[name="end_date"]');
    const box = document.getElementById('leave-conflict-check');
    const title = document.getElementById('leave-conflict-check-title');
    const list = document.getElementById('leave-conflict-check-list');
    const serverBlock = document.getElementById('leave-conflicts');
    const endpoint = @json(route('staff.leave-conflicts'));

    // Guards against out-of-order responses when the dates change quickly.
    let requestSeq = 0;

    function removeConfirmFlag() {
        form.querySelectorAll('input[data-leave-conflict-flag]').forEach(function (input) {
            input.remove();
        });
    }

    // The hidden flag is what makes the next submit file immediately: the
    // staff member has already seen the warning for these exact dates.
    function addConfirmFlag() {
        if (form.querySelector('input[data-leave-conflict-flag]')) {
            return;
        }

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'confirm_conflicts';
        input.value = '1';
        input.setAttribute('data-leave-conflict-flag', '1');
        form.appendChild(input);
    }

    function hideWarning() {
        box.classList.add('d-none');
        list.textContent = '';
    }

    function showWarning(conflicts) {
        list.textContent = '';

        conflicts.forEach(function (conflict) {
            const item = document.createElement('li');
            const date = document.createElement('strong');
            date.textContent = conflict.date_label;
            item.appendChild(date);
            item.appendChild(document.createTextNode(
                ' at ' + conflict.time_label + ' — ' + conflict.service + ' (' + conflict.client + ')'
            ));
            list.appendChild(item);
        });

        title.textContent = 'This leave overlaps '
            + (conflicts.length === 1 ? 'an existing booking' : 'existing bookings') + ':';
        box.classList.remove('d-none');
    }

    function checkConflicts() {
        // A flashed post-submit warning describes the dates it was rendered
        // for; once a date changes it is stale.
        if (serverBlock) {
            serverBlock.classList.add('d-none');
        }

        removeConfirmFlag();
        hideWarning();

        const startDate = startInput.value;
        const endDate = endInput.value;

        // Wait until both dates are chosen.
        if (!startDate || !endDate) {
            return;
        }

        const seq = ++requestSeq;

        fetch(endpoint + '?start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Conflict check failed');
                }
                return response.json();
            })
            .then(function (data) {
                if (seq !== requestSeq) {
                    return;
                }

                const conflicts = data.conflicts || [];

                if (conflicts.length > 0) {
                    showWarning(conflicts);
                    addConfirmFlag();
                }
            })
            .catch(function () {
                // Leave the flag unset: the server-side post-submit gate is
                // still there for no-JS / failed-request cases.
            });
    }

    startInput.addEventListener('change', checkConflicts);
    endInput.addEventListener('change', checkConflicts);

    @if(session('leaveConflicts'))
    // Post-submit fallback warning was flashed: reopen the modal with the
    // typed values so the staff member sees it (the fallback gate stays).
    new bootstrap.Modal(modal).show();
    @endif
});
</script>
@endpush
