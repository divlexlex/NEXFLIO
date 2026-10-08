@extends('layouts.admin')

@section('title', 'Appointments')

@push('head')
<style>
    /* Calendar events — light chip colours come inline from
       AppointmentController@feed; the .nx-evt-* classNames drive the dark-mode
       overrides and shape. */
    .fc .fc-event.nx-evt {
        padding: 1px 5px; font-size: .78rem; font-weight: 500;
        border-radius: 5px; border-width: 1px; cursor: pointer;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        max-width: 100%; display: block;
    }
    .fc .fc-event.nx-evt .fc-event-time { font-weight: 700; white-space: nowrap; }
    .fc .fc-event.nx-evt-cancelled,
    .fc .fc-event.nx-evt-no-show { text-decoration: line-through; }

    /* Constrain events to their day cell so bars never bleed into adjacent
       columns — especially across month boundaries (Sep 30 → Oct 1, etc.). */
    .fc .fc-daygrid-day,
    .fc .fc-daygrid-day-frame,
    .fc .fc-daygrid-day-events { overflow: hidden; }
    .fc .fc-daygrid-day { position: relative; }
    .fc .fc-daygrid-day-events { position: relative; }

    /* Grid follows the admin theme in both modes. */
    .fc { --fc-border-color: var(--spa-tan); --fc-today-bg-color: rgba(201, 162, 75, .14); }
    .fc .fc-toolbar-title,
    .fc .fc-col-header-cell-cushion,
    .fc .fc-daygrid-day-number,
    .fc .fc-list-day-text { color: var(--spa-text); }

    html[data-bs-theme="dark"] .fc {
        --fc-page-bg-color: transparent;
        --fc-neutral-bg-color: #1c150f;
        --fc-list-event-hover-bg-color: #2a1f16;
        --fc-today-bg-color: rgba(201, 162, 75, .12);
    }
    html[data-bs-theme="dark"] .fc a,
    html[data-bs-theme="dark"] .fc .fc-daygrid-day-number { color: var(--spa-text); }
    html[data-bs-theme="dark"] .fc .fc-daygrid-day.fc-day-other { opacity: .45; }

    /* Dark-mode chip colours (win over the inline light colours). */
    html[data-bs-theme="dark"] .fc .fc-event.nx-evt-unverified { background-color: #4a3413 !important; border-color: #6b4d1e !important; color: #f2c88f !important; }
    html[data-bs-theme="dark"] .fc .fc-event.nx-evt-booked     { background-color: #1e3a2a !important; border-color: #2f5a41 !important; color: #8fd6a9 !important; }
    html[data-bs-theme="dark"] .fc .fc-event.nx-evt-in-service { background-color: #1f3358 !important; border-color: #35528a !important; color: #9cc0ff !important; }
    html[data-bs-theme="dark"] .fc .fc-event.nx-evt-completed  { background-color: #362a4c !important; border-color: #52407a !important; color: #c7aef0 !important; }
    html[data-bs-theme="dark"] .fc .fc-event.nx-evt-cancelled  { background-color: #2c2c2c !important; border-color: #444 !important;    color: #9a9a9a !important; }
    html[data-bs-theme="dark"] .fc .fc-event.nx-evt-no-show    { background-color: #40302e !important; border-color: #5e4643 !important; color: #d9a49b !important; }
    html[data-bs-theme="dark"] .fc .fc-event.nx-evt-walkin     { background-color: #1f3358 !important; border-color: #35528a !important; color: #9cc0ff !important; }

    .nx-legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .nx-drawer-row { display: flex; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid var(--spa-tan); }
    .nx-drawer-row .bi { color: var(--spa-gold); }
    .nx-drawer-label { color: var(--spa-latte); font-size: .8rem; min-width: 92px; }
    html[data-bs-theme="dark"] .nx-drawer-label { color: #b79a7a; }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Appointments</h1>
        <p class="text-muted small mb-0">Click any appointment for details and actions. Click a day to see everything on it.</p>
    </div>
    <button class="btn btn-spa" data-bs-toggle="modal" data-bs-target="#walkInModal">
        <i class="bi bi-person-plus me-1"></i>Walk-in Entry
    </button>
</div>

{{-- LEGEND --}}
<div class="d-flex flex-wrap gap-3 mb-2 small">
    @foreach([
        'Confirmed' => ['#dcf0e2', '#a9d9bc'], 'Pending' => ['#fcebd2', '#ecc78f'],
        'In service' => ['#deeafe', '#aec8f7'], 'Completed' => ['#eae1f6', '#cdb8ec'],
        'Walk-in' => ['#deeafe', '#aec8f7'], 'Cancelled' => ['#ececec', '#d9d9d9'],
    ] as $label => $c)
        <span class="d-inline-flex align-items-center gap-2">
            <span class="nx-legend-dot" style="background: {{ $c[0] }}; border: 1px solid {{ $c[1] }};"></span>{{ $label }}
        </span>
    @endforeach
</div>

<div class="card p-3 mb-4">
    <div id="calendar"></div>
</div>

<div class="card p-3">
    <form method="GET" class="d-flex gap-2 mb-3">
        <select name="status" class="form-select w-auto" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach($statuses as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                    {{ $status->label() }}
                </option>
            @endforeach
        </select>
    </form>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Service</th>
                    <th>Staff</th>
                    <th>When</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($appointments as $appointment)
                    <tr>
                        <td>
                            {{ $appointment->clientName() ?? '—' }}
                            @if($appointment->user_id === null)
                                <span class="badge text-bg-secondary">walk-in</span>
                            @endif
                        </td>
                        <td>{{ $appointment->service->name ?? '—' }}</td>
                        <td>{{ $appointment->personnel->name ?? 'No preferred personnel' }}</td>
                        <td>{{ $appointment->appointment_date->format('M j, Y') }} · {{ $appointment->start_time }}</td>
                        <td><span class="badge text-bg-light border">{{ $appointment->status->label() }}</span></td>
                        <td>
                            @if($appointment->payment)
                                <span class="badge {{ match($appointment->payment->status->value) {
                                    'verified' => 'text-bg-success',
                                    'rejected' => 'text-bg-danger',
                                    default => 'text-bg-warning',
                                } }}">{{ ucfirst($appointment->payment->status->value) }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @unless($appointment->status->isTerminal())
                                <button class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="modal" data-bs-target="#overrideModal"
                                        data-id="{{ $appointment->id }}"
                                        data-date="{{ $appointment->appointment_date->toDateString() }}"
                                        data-time="{{ substr($appointment->start_time, 0, 5) }}"
                                        data-personnel="{{ $appointment->personnel_id }}">
                                    Override
                                </button>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No appointments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $appointments->links() }}
</div>

{{-- APPOINTMENT DETAILS DRAWER --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="apptDrawer" style="width: 380px;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title">Appointment Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
                 style="width:44px;height:44px;background:var(--spa-gold);color:#fff;" id="dw-initials">–</div>
            <div>
                <div class="fw-semibold" id="dw-client">–</div>
                <div class="small text-muted"><span id="dw-phone">–</span></div>
            </div>
            <span class="badge text-bg-light border ms-auto d-none" id="dw-returning">Returning</span>
        </div>

        <div class="nx-drawer-row"><span class="nx-drawer-label"><i class="bi bi-scissors me-1"></i>Service</span><span><span id="dw-service">–</span><br><span class="small text-muted" id="dw-servicemeta"></span></span></div>
        <div class="nx-drawer-row"><span class="nx-drawer-label"><i class="bi bi-tag me-1"></i>Type</span><span id="dw-type">–</span></div>
        <div class="nx-drawer-row"><span class="nx-drawer-label"><i class="bi bi-calendar3 me-1"></i>When</span><span><span id="dw-date">–</span><br><span class="small text-muted" id="dw-time"></span></span></div>
        <div class="nx-drawer-row"><span class="nx-drawer-label"><i class="bi bi-person-badge me-1"></i>Staff</span><span id="dw-staff">–</span></div>
        <div class="nx-drawer-row"><span class="nx-drawer-label"><i class="bi bi-flag me-1"></i>Status</span><span class="badge text-bg-light border" id="dw-status">–</span></div>
        <div class="nx-drawer-row" id="dw-payment-row"><span class="nx-drawer-label"><i class="bi bi-cash-coin me-1"></i>Payment</span><span><span id="dw-payment">–</span> <a href="#" target="_blank" class="ms-1 small d-none" id="dw-proof">View proof <i class="bi bi-box-arrow-up-right"></i></a></span></div>
        <div class="nx-drawer-row" id="dw-notes-row"><span class="nx-drawer-label"><i class="bi bi-sticky me-1"></i>Notes</span><span class="small" id="dw-notes"></span></div>

        <div class="d-grid gap-2 mt-3" id="dw-actions"></div>

        {{-- One shared status form the action buttons submit --}}
        <form method="POST" id="dw-statusForm" class="d-none">
            @csrf @method('PATCH')
            <input type="hidden" name="status" id="dw-status-target">
            <input type="hidden" name="rejection_reason" id="dw-reason">
        </form>
    </div>
</div>

{{-- DAY DETAILS MODAL --}}
<div class="modal fade" id="dayModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="day-title">Day</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="day-list" class="d-flex flex-column gap-2"></div>
                <p class="text-muted small mb-0 d-none" id="day-empty">Nothing booked on this day.</p>
            </div>
        </div>
    </div>
</div>

{{-- WALK-IN MODAL --}}
<div class="modal fade" id="walkInModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.appointments.walk-in') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Walk-in Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Cash is collected on the spot, so the booking is created already verified.
                </p>
                <div class="mb-2">
                    <label class="form-label">Client name</label>
                    <input name="walk_in_name" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label">Phone (optional)</label>
                    <input name="walk_in_phone" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label">Service</label>
                    <select name="service_id" class="form-select" required>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}">
                                {{ $service->name }} — ₱{{ number_format($service->price, 2) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label">Staff</label>
                    <select name="personnel_id" class="form-select" required>
                        @foreach($personnel as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Date</label>
                        <input type="date" name="appointment_date" class="form-control"
                               value="{{ now()->toDateString() }}" min="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Time</label>
                        <input type="time" name="start_time" class="form-control" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa">Create Booking</button>
            </div>
        </form>
    </div>
</div>

{{-- OVERRIDE MODAL --}}
<div class="modal fade" id="overrideModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="overrideForm" class="modal-content">
            @csrf
            @method('PATCH')
            <div class="modal-header">
                <h5 class="modal-title">Override Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Reschedule or reassign. The change is recorded in the activity log and the
                    client is notified.
                </p>
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Date</label>
                        <input type="date" name="appointment_date" id="ov-date" class="form-control" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Time</label>
                        <input type="time" name="start_time" id="ov-time" class="form-control" required>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Staff</label>
                    <select name="personnel_id" id="ov-personnel" class="form-select" required>
                        @foreach($personnel as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa">Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- ASSIGN STAFF MODAL --}}
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="assignForm" class="modal-content">
            @csrf
            @method('PATCH')
            <div class="modal-header">
                <h5 class="modal-title">Assign Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Assign a staff member to this appointment. The client will be notified.
                </p>
                <div class="mb-2">
                    <label class="form-label">Staff</label>
                    <select name="personnel_id" id="assign-personnel" class="form-select" required>
                        @foreach($personnel as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa">Assign</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const statusBase = "{{ url('admin/appointments') }}";
        const drawerEl = document.getElementById('apptDrawer');
        const drawer = new bootstrap.Offcanvas(drawerEl);
        const dayModal = new bootstrap.Modal(document.getElementById('dayModal'));

        const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
            initialView: 'dayGridMonth',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
            events: "{{ route('admin.appointments.feed') }}",
            height: 640,
            dayMaxEvents: 3,
            eventDisplay: 'block',
            eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },
            eventClick: (info) => { info.jsEvent.preventDefault(); openDrawer(info.event); },
            dateClick: (info) => openDay(info.dateStr),
            eventDidMount(info) {
                const cell = info.el.closest('.fc-daygrid-day');
                if (cell) {
                    info.el.style.maxWidth = cell.offsetWidth + 'px';
                    info.el.style.boxSizing = 'border-box';
                }
            },
        });
        calendar.render();

        function esc(s) { return (s == null ? '' : String(s)).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

        function openDrawer(event) {
            const p = event.extendedProps;
            document.getElementById('dw-initials').textContent = (p.client || '?').trim().charAt(0).toUpperCase();
            document.getElementById('dw-client').textContent = p.client || '—';
            document.getElementById('dw-phone').textContent = p.phone || 'No phone on file';
            document.getElementById('dw-returning').classList.toggle('d-none', !p.returning);
            document.getElementById('dw-service').textContent = p.service || '—';
            document.getElementById('dw-servicemeta').textContent =
                [p.durationMins ? p.durationMins + ' min' : null, p.price != null ? '₱' + Number(p.price).toLocaleString(undefined, {minimumFractionDigits: 2}) : null]
                    .filter(Boolean).join(' · ');
            document.getElementById('dw-type').textContent = p.bookingType || '—';
            document.getElementById('dw-date').textContent = p.dateLabel || '—';
            document.getElementById('dw-time').textContent = p.timeLabel || '';
            document.getElementById('dw-staff').textContent = p.personnel || 'No preferred personnel';
            document.getElementById('dw-status').textContent = p.statusLabel || '—';

            const payRow = document.getElementById('dw-payment-row');
            const proof = document.getElementById('dw-proof');
            if (p.payment) {
                payRow.classList.remove('d-none');
                document.getElementById('dw-payment').textContent =
                    p.payment.label + (p.payment.amount != null ? ' · ₱' + Number(p.payment.amount).toLocaleString(undefined, {minimumFractionDigits: 2}) : '');
                if (p.payment.proofUrl) { proof.href = p.payment.proofUrl; proof.classList.remove('d-none'); }
                else { proof.classList.add('d-none'); }
            } else {
                payRow.classList.add('d-none');
            }

            const notesRow = document.getElementById('dw-notes-row');
            if (p.notes) { notesRow.classList.remove('d-none'); document.getElementById('dw-notes').textContent = p.notes; }
            else { notesRow.classList.add('d-none'); }

            const form = document.getElementById('dw-statusForm');
            form.action = statusBase + '/' + event.id + '/status';
            const actions = document.getElementById('dw-actions');
            actions.innerHTML = '';
            (p.actions || []).forEach(a => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-' + a.style;
                btn.textContent = a.label;
                btn.addEventListener('click', () => {
                    if (a.reason) {
                        const r = window.prompt('Reason for rejecting / cancelling this booking:');
                        if (r === null) return;
                        document.getElementById('dw-reason').value = r;
                    }
                    document.getElementById('dw-status-target').value = a.to;
                    form.submit();
                });
                actions.appendChild(btn);
            });
            if (!p.isTerminal) {
                const assignBtn = document.createElement('button');
                assignBtn.type = 'button';
                assignBtn.className = 'btn btn-outline-secondary';
                assignBtn.textContent = 'Assign Staff';
                assignBtn.addEventListener('click', () => {
                    document.getElementById('assignForm').action = statusBase + '/' + event.id + '/assign-personnel';
                    document.getElementById('assign-personnel').value = p.personnelId || '';
                    new bootstrap.Modal(document.getElementById('assignModal')).show();
                });
                actions.appendChild(assignBtn);
            }
            if (!(p.actions || []).length) {
                actions.innerHTML = '<p class="text-muted small mb-0">No actions available for a ' + esc(p.statusLabel).toLowerCase() + ' appointment.</p>';
            }

            drawer.show();
        }

        function openDay(dateStr) {
            const events = calendar.getEvents().filter(e => e.startStr.slice(0, 10) === dateStr);
            const list = document.getElementById('day-list');
            const empty = document.getElementById('day-empty');
            document.getElementById('day-title').textContent =
                new Date(dateStr + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
            list.innerHTML = '';
            empty.classList.toggle('d-none', events.length > 0);
            events.sort((a, b) => a.startStr.localeCompare(b.startStr)).forEach(e => {
                const p = e.extendedProps;
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'btn btn-light border text-start d-flex justify-content-between align-items-center';
                row.innerHTML = '<span><strong>' + esc(p.timeLabel ? p.timeLabel.split(' – ')[0] : '') + '</strong> · ' +
                    esc(p.client) + '<br><span class="small text-muted">' + esc(p.service) + ' · ' + esc(p.bookingType) + '</span></span>' +
                    '<span class="badge text-bg-light border">' + esc(p.statusLabel) + '</span>';
                row.addEventListener('click', () => { dayModal.hide(); openDrawer(e); });
                list.appendChild(row);
            });
            dayModal.show();
        }

        const overrideModal = document.getElementById('overrideModal');
        overrideModal.addEventListener('show.bs.modal', (event) => {
            const btn = event.relatedTarget;
            document.getElementById('overrideForm').action = statusBase + '/' + btn.dataset.id + '/override';
            document.getElementById('ov-date').value = btn.dataset.date;
            document.getElementById('ov-time').value = btn.dataset.time;
            document.getElementById('ov-personnel').value = btn.dataset.personnel;
        });
    });
</script>
@endpush
