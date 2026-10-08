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
                <p class="mb-3">Timed in at <strong>{{ $todayAttendance->time_in->format('g:i A') }}</strong>.</p>
                <form method="POST" action="{{ route('staff.attendance.time-out') }}">
                    @csrf
                    <button class="btn btn-outline-secondary"><i class="bi bi-box-arrow-right me-1"></i>Time Out</button>
                </form>
            @else
                <p class="mb-0">
                    Timed in at <strong>{{ $todayAttendance->time_in->format('g:i A') }}</strong>,
                    timed out at <strong>{{ $todayAttendance->time_out->format('g:i A') }}</strong>.
                </p>
                <p class="text-muted small mb-0">Shift complete — see you next time.</p>
            @endif
        </div>
    </div>

    {{-- EARNINGS --}}
    <div class="col-md-6">
        <div class="card p-3 h-100">
            <h2 class="h6 text-uppercase text-muted mb-3">Commissions This Month</h2>
            <p class="display-6 mb-0">₱{{ number_format($commissionsThisMonth, 2) }}</p>
            <p class="text-muted small mb-0">Based on your completed appointments this month.</p>
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
    <div class="col-lg-6">
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

    {{-- LEAVE REQUESTS --}}
    <div class="col-lg-6">
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
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" min="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" min="{{ now()->toDateString() }}" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" required>
                        @foreach(\App\Models\LeaveRequest::TYPES as $type)
                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" rows="3" required></textarea>
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
