@extends('layouts.admin')

@section('title', 'Availability')

@section('content')
<h1 class="h3 mb-4">Availability Management</h1>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-hours" type="button">
            <i class="bi bi-clock me-1"></i>Business Hours
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-staff" type="button">
            <i class="bi bi-people me-1"></i>Personnel Schedules
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-services" type="button">
            <i class="bi bi-stars me-1"></i>Service Hours
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-blocked" type="button">
            <i class="bi bi-x-octagon me-1"></i>Blocked Slots
        </button>
    </li>
</ul>

<div class="tab-content">
    {{-- ── TAB 1: BUSINESS HOURS ────────────────────────────────────── --}}
    <div class="tab-pane fade show active" id="tab-hours">
        <div class="card p-3">
            <p class="text-muted small mb-3">Set the store's opening and closing times for each day. These apply to all booking slots.</p>
            <form method="POST" action="{{ route('admin.availability.business-hours') }}">
                @csrf
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr><th>Day</th><th>Open</th><th>Close</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @php($days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'])
                            @foreach($days as $i => $day)
                                @php($hours = $businessHours->firstWhere('day_of_week', $i))
                                <tr>
                                    <td class="fw-semibold">{{ $day }}</td>
                                    <td>
                                        <input type="time" name="hours[{{ $i }}][open_at]"
                                               value="{{ $hours?->open_at ? \Carbon\Carbon::parse($hours->open_at)->format('H:i') : '10:00' }}"
                                               class="form-control form-control-sm w-auto"
                                               {{ !$hours?->is_open ? 'disabled' : '' }}>
                                    </td>
                                    <td>
                                        <input type="time" name="hours[{{ $i }}][close_at]"
                                               value="{{ $hours?->close_at ? \Carbon\Carbon::parse($hours->close_at)->format('H:i') : '21:00' }}"
                                               class="form-control form-control-sm w-auto"
                                               {{ !$hours?->is_open ? 'disabled' : '' }}>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                   name="hours[{{ $i }}][is_open]" value="1"
                                                   {{ ($hours?->is_open ?? true) ? 'checked' : '' }}
                                                   onchange="this.closest('tr').querySelectorAll('input[type=time]').forEach(el => el.disabled = !this.checked)">
                                            <label class="form-check-label small">{{ ($hours?->is_open ?? true) ? 'Open' : 'Closed' }}</label>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-spa mt-2">Save Business Hours</button>
            </form>
        </div>
    </div>

    {{-- ── TAB 2: PERSONNEL SCHEDULES ───────────────────────────────── --}}
    <div class="tab-pane fade" id="tab-staff">
        <div class="card p-3">
            <p class="text-muted small mb-3">Set each staff member's available days and hours. If no schedule is set, they follow business hours.</p>
            <div class="mb-3">
                <label class="form-label fw-semibold">Select Staff Member</label>
                <select id="staffSelect" class="form-select w-auto" onchange="loadStaffSchedule()">
                    <option value="">— Choose staff —</option>
                    @foreach($staff as $s)
                        <option value="{{ $s->id }}">{{ $s->fullName() }} ({{ $s->staffProfile->position ?? 'Staff' }})</option>
                    @endforeach
                </select>
            </div>
            <div id="staffScheduleForm" style="display:none;">
                <form method="POST" action="{{ route('admin.availability.staff-schedule') }}">
                    @csrf
                    <input type="hidden" name="user_id" id="scheduleUserId">
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr><th>Day</th><th>Available</th><th>Start</th><th>End</th></tr>
                            </thead>
                            <tbody id="scheduleBody"></tbody>
                        </table>
                    </div>
                    <button class="btn btn-spa mt-2">Save Schedule</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── TAB 3: SERVICE HOURS ─────────────────────────────────────── --}}
    <div class="tab-pane fade" id="tab-services">
        <div class="card p-3">
            <p class="text-muted small mb-3">Restrict specific services to certain hours. Leave blank to follow business hours.</p>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr><th>Service</th><th>Available From</th><th>Available Until</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($services as $service)
                            <tr>
                                <td>
                                    <strong>{{ $service->name }}</strong>
                                    <div class="small text-muted">{{ $service->category }} · {{ $service->duration_minutes }} min</div>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.availability.service-hours') }}" class="d-flex align-items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="service_id" value="{{ $service->id }}">
                                        <input type="time" name="available_from"
                                               value="{{ $service->available_from ? \Carbon\Carbon::parse($service->available_from)->format('H:i') : '' }}"
                                               class="form-control form-control-sm w-auto" placeholder="10:00">
                                </td>
                                <td>
                                        <input type="time" name="available_until"
                                               value="{{ $service->available_until ? \Carbon\Carbon::parse($service->available_until)->format('H:i') : '' }}"
                                               class="form-control form-control-sm w-auto" placeholder="21:00">
                                </td>
                                <td>
                                        <button class="btn btn-sm btn-spa">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">No active services.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── TAB 4: BLOCKED SLOTS ────────────────────────────────────── --}}
    <div class="tab-pane fade" id="tab-blocked">
        <div class="card p-3 mb-4">
            <p class="text-muted small mb-3">Block specific dates or time slots from being bookable. Leave times blank to block the entire day.</p>
            <form method="POST" action="{{ route('admin.availability.blocked-slots.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-auto">
                    <label class="form-label small mb-0">Date</label>
                    <input type="date" name="blocked_date" class="form-control form-control-sm" min="{{ now()->toDateString() }}" required>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">From</label>
                    <input type="time" name="start_time" class="form-control form-control-sm">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">To</label>
                    <input type="time" name="end_time" class="form-control form-control-sm">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">Staff (optional)</label>
                    <select name="personnel_id" class="form-select form-select-sm">
                        <option value="">All staff</option>
                        @foreach($staff as $s)
                            <option value="{{ $s->id }}">{{ $s->fullName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">Reason</label>
                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="Holiday, maintenance...">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-spa">Block</button>
                </div>
            </form>
        </div>

        <div class="card p-3">
            <h6 class="mb-3">Upcoming Blocked Slots</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr><th>Date</th><th>Time</th><th>Staff</th><th>Reason</th><th>Blocked by</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($blockedSlots as $slot)
                            <tr>
                                <td>{{ $slot->blocked_date->format('M j, Y') }}</td>
                                <td>
                                    @if($slot->start_time && $slot->end_time)
                                        {{ \Carbon\Carbon::parse($slot->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($slot->end_time)->format('g:i A') }}
                                    @else
                                        <span class="text-muted">All day</span>
                                    @endif
                                </td>
                                <td>{{ $slot->personnel?->fullName() ?? 'All staff' }}</td>
                                <td>{{ $slot->reason ?? '—' }}</td>
                                <td class="small text-muted">{{ $slot->creator?->name ?? '—' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.availability.blocked-slots.destroy', $slot->id) }}" class="d-inline"
                                          onsubmit="return confirm('Remove this blocked slot?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">No blocked slots.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const staffSchedules = @json($schedules);

    function loadStaffSchedule() {
        const userId = document.getElementById('staffSelect').value;
        const form = document.getElementById('staffScheduleForm');
        const tbody = document.getElementById('scheduleBody');
        document.getElementById('scheduleUserId').value = userId;

        if (!userId) { form.style.display = 'none'; return; }
        form.style.display = 'block';

        const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        tbody.innerHTML = '';

        days.forEach((day, i) => {
            const existing = staffSchedules.find(s => s.user_id == userId && s.day_of_week === i);
            const checked = existing ? existing.is_available : true;
            const start = existing ? (existing.start_time || '').substring(0,5) : '10:00';
            const end = existing ? (existing.end_time || '').substring(0,5) : '21:00';

            tbody.innerHTML += `
                <tr>
                    <td class="fw-semibold">${day}</td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="schedule[${i}][is_available]" value="1"
                                   ${checked ? 'checked' : ''}
                                   onchange="toggleDay(this, ${i})">
                        </div>
                    </td>
                    <td><input type="time" name="schedule[${i}][start_time]" value="${start}" class="form-control form-control-sm w-auto" ${!checked ? 'disabled' : ''}></td>
                    <td><input type="time" name="schedule[${i}][end_time]" value="${end}" class="form-control form-control-sm w-auto" ${!checked ? 'disabled' : ''}></td>
                </tr>`;
        });
    }

    function toggleDay(cb, dayIndex) {
        const row = cb.closest('tr');
        row.querySelectorAll('input[type=time]').forEach(el => el.disabled = !cb.checked);
    }
</script>
@endpush
