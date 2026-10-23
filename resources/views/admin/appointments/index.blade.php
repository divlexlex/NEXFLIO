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
    .fc { --fc-border-color: var(--nex-border); --fc-today-bg-color: rgba(201, 162, 75, .14); }
    .fc .fc-toolbar-title,
    .fc .fc-col-header-cell-cushion,
    .fc .fc-daygrid-day-number,
    .fc .fc-list-day-text { color: var(--nex-text); }

    html[data-bs-theme="dark"] .fc {
        --fc-page-bg-color: transparent;
        --fc-neutral-bg-color: #1c150f;
        --fc-list-event-hover-bg-color: #2a1f16;
        --fc-today-bg-color: rgba(201, 162, 75, .12);
    }
    html[data-bs-theme="dark"] .fc a,
    html[data-bs-theme="dark"] .fc .fc-daygrid-day-number { color: var(--nex-text); }
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
    .nx-drawer-row { display: flex; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid var(--nex-border); }
    .nx-drawer-row .bi { color: var(--nex-accent); }
    .nx-drawer-label { color: var(--nex-muted); font-size: .8rem; min-width: 92px; }
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

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" data-nx-flash>
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

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
                <option value="{{ $status->value }}" @selected(($currentStatus ?? '') === $status->value)>
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
                        <td>{{ $appointment->displayServiceName() }}</td>
                        <td>{{ $appointment->personnel->name ?? 'No preferred personnel' }}</td>
                        <td>{{ $appointment->appointment_date->format('M j, Y') }} · {{ \Carbon\Carbon::parse($appointment->start_time)->format('g:i A') }}</td>
                        <td><span class="badge text-bg-light border">{{ $appointment->status->label() }}</span></td>
                        <td>
                            @if($appointment->payment)
                                <span class="badge {{ match($appointment->payment->status->value) {
                                    'verified' => 'text-bg-success',
                                    'rejected' => 'text-bg-danger',
                                    default => 'text-bg-warning',
                                } }}">{{ ucfirst($appointment->payment->status->value) }}</span>
                                <div class="small text-muted mt-1">{{ $appointment->paymentState() }}</div>
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
                                        data-personnel="{{ $appointment->personnel_id }}"
                                        data-category="{{ $appointment->service?->category }}"
                                        data-eligible-positions="{{ json_encode(\App\Models\Position::namesForServiceCategory($appointment->service?->category ?? '')) }}">
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
                 style="width:44px;height:44px;background:var(--nex-primary-bg);color:var(--nex-primary-text);" id="dw-initials">–</div>
            <div>
                <div class="fw-semibold" id="dw-client">–</div>
                <div class="small text-muted"><span id="dw-phone">–</span></div>
            </div>
            <span class="badge text-bg-light border ms-auto d-none" id="dw-returning">Returning</span>
        </div>

        <div class="nx-drawer-row"><span class="nx-drawer-label"><i class="bi bi-hash me-1"></i>Appointment ID</span><span class="fw-semibold" id="dw-refcode">–</span></div>
        <div class="nx-drawer-row"><span class="nx-drawer-label"><i class="bi bi-scissors me-1"></i>Service</span><span><span id="dw-service">–</span><br><span class="small text-muted" id="dw-servicemeta"></span><span class="d-flex flex-column gap-1 mt-1" id="dw-availed-list"></span></span></div>
        <div class="nx-drawer-row"><span class="nx-drawer-label"><i class="bi bi-tag me-1"></i>Type</span><span id="dw-type">–</span></div>
        <div class="nx-drawer-row"><span class="nx-drawer-label"><i class="bi bi-calendar3 me-1"></i>When</span><span><span id="dw-date">–</span><br><span class="small text-muted" id="dw-time"></span></span></div>
        <div class="nx-drawer-row"><span class="nx-drawer-label"><i class="bi bi-person-badge me-1"></i>Staff</span><span id="dw-staff">–</span></div>
        <div class="nx-drawer-row"><span class="nx-drawer-label"><i class="bi bi-flag me-1"></i>Status</span><span class="badge text-bg-light border" id="dw-status">–</span></div>
        <div class="nx-drawer-row" id="dw-payment-row"><span class="nx-drawer-label"><i class="bi bi-cash-coin me-1"></i>Payment</span><span><span id="dw-payment">–</span> <a href="#" target="_blank" class="ms-1 small d-none" id="dw-proof">View proof <i class="bi bi-box-arrow-up-right"></i></a><br><span class="small text-muted" id="dw-paystate"></span></span></div>
        <div class="nx-drawer-row" id="dw-notes-row"><span class="nx-drawer-label"><i class="bi bi-sticky me-1"></i>Notes</span><span class="small" id="dw-notes"></span></div>

        {{-- Services added to this same appointment AFTER booking (same
             Appointment ID), READ-ONLY here. Creation-time avails live under
             Service above; this row lists only the ones added later from the
             Payments add flow, so it stays hidden until at least one exists.
             Their prices are already folded into the Balance due shown below.
             Removing is still offered while the appointment is Booked or
             In-Service (Completed is a closed money record). --}}
        <div class="nx-drawer-row d-none" id="dw-added-row">
            <span class="nx-drawer-label"><i class="bi bi-plus-slash-minus me-1"></i>Additional services</span>
            <span class="small">
                <span class="d-flex flex-column gap-1" id="dw-added-list"></span>
            </span>
        </div>

        {{-- Money decisions happen in Payments: for an Unverified booking
             with a pending payment the drawer offers no status actions —
             just this pointer. Branch settlement lives on the Payments page. --}}
        <div class="alert alert-info d-none mt-3 mb-0" id="dw-verify-hint">
            <i class="bi bi-info-circle me-1"></i>
            Payment pending — verify or reject it in
            <a href="{{ route('admin.payments', ['status' => 'pending']) }}">Payments</a>.
        </div>

        {{-- Pending cancellation: replaces the operation buttons (and the
             status badge text) while a cancellation request awaits review.
             Everything else in the drawer stays visible. --}}
        <div class="alert alert-warning d-none mt-3 mb-0" id="dw-cancel-notice">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <span class="fw-semibold">Cancellation requested — pending review in Change Requests.</span>
            <span class="d-block small mt-1 d-none" id="dw-cancel-reason-wrap">Reason: <span id="dw-cancel-reason"></span></span>
            <a href="{{ route('admin.appointment-requests') }}" class="btn btn-sm btn-warning mt-2">Review in Change Requests</a>
        </div>

        {{-- No-personnel warning: shown directly above the action buttons
             when the appointment has no assigned staff but is still
             actionable (Unverified/Booked/In-Service). Start service is
             already unavailable without personnel — this is the visible
             explanation. --}}
        <div class="alert alert-warning d-none mt-3 mb-0" id="dw-staff-notice">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <span class="fw-semibold">No personnel assigned — assign a staff member before this service can be started.</span>
        </div>

        <div class="d-grid gap-2 mt-3" id="dw-actions"></div>

        {{-- One shared status form the action buttons submit --}}
        <form method="POST" id="dw-statusForm" class="d-none">
            @csrf @method('PATCH')
            <input type="hidden" name="status" id="dw-status-target">
            <input type="hidden" name="rejection_reason" id="dw-reason">
        </form>

        {{-- Hidden DELETE form the per-row Remove buttons submit --}}
        <form method="POST" id="dw-removeServiceForm" class="d-none">
            @csrf @method('DELETE')
        </form>
    </div>
</div>

{{-- DRAWER CONFIRM MODALS: "Mark no-show" and "Cancel" open one of these
     first; only Confirm submits the shared status form below to the
     existing endpoint (same transition + payment side effects). Start
     service / Assign Staff are never gated here. --}}
<div class="modal fade" id="dwNoShowModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark as no-show?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="dw-noshow-summary"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-spa" id="dw-noshow-confirm">Confirm</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dwCancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel this appointment?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="dw-cancel-summary"></p>
                <label class="form-label" for="dw-cancel-reason-input">Reason <span class="text-muted">(optional)</span></label>
                <textarea id="dw-cancel-reason-input" class="form-control" rows="2" placeholder="Why is this booking being cancelled?"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-spa" id="dw-cancel-confirm">Confirm</button>
            </div>
        </div>
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
                    Walk-ins start as Booked with the full amount as balance — record the payment
                    in Payments when the client pays; check-out appears once the balance is settled.
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
                    <select name="service_id" id="walkin-service" class="form-select" required>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}">
                                {{ $service->name }} — ₱{{ number_format($service->price, 2) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2" id="walkin-staff-single">
                    <label class="form-label">Staff</label>
                    {{-- Options filled by JS from the selected service category. --}}
                    <select name="personnel_id" id="walkin-personnel" class="form-select" required></select>
                </div>
                <div id="walkin-staff-package" class="d-none"></div>
                {{-- Avail services on the same visit: repeatable regular
                     services (active, non-package) with an optional variant
                     and staff pick. Their durations add to the main service's
                     when the closing-time check runs. --}}
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label mb-0">Avail services <span class="text-muted">(add 1 or more service)</span></label>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="walkin-extra-add">
                            <i class="bi bi-plus-lg me-1"></i>Add another service
                        </button>
                    </div>
                    <div id="walkin-extras" class="mt-2"></div>
                </div>
                <div class="row">
                    <div class="col mb-2">
                        <label class="form-label">Date</label>
                        <input type="date" name="appointment_date" id="walkin-date" class="form-control"
                               value="{{ now()->toDateString() }}" min="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col mb-2">
                        <label class="form-label">Time</label>
                        <input type="time" name="start_time" id="walkin-time" class="form-control" required>
                        <div class="text-danger small mt-1 d-none" id="walkin-time-hint"></div>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa" id="walkin-submit">Create Booking</button>
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
                <div class="mb-2" id="assign-single-wrap">
                    <label class="form-label">Staff</label>
                    <select name="personnel_id" id="assign-personnel" class="form-select">
                        @foreach($personnel as $member)
                            <option value="{{ $member->id }}">{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="assign-package-wrap" class="d-none"></div>
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

        // Shared: staff who offer positions linked to a service category
        // (Facial → Facial Technician, etc.). No linked positions or no
        // matching employees → empty list (never fall back to all staff).
        const staffCatalog = @json($personnelJson);
        function staffForEligiblePositions(eligiblePositions) {
            const names = eligiblePositions || [];
            // Strict filter only — never keep a previously selected staff
            // member who does not offer this service category (e.g. a Massage
            // Tech left over after switching to Gel Manicure / Nails).
            if (!names.length) return [];
            return staffCatalog.filter((s) => names.includes(s.position));
        }
        function fillStaffSelect(select, staff, selectedId, emptyLabel) {
            if (!select) return;
            if (!staff.length) {
                select.innerHTML = '<option value="">' + (emptyLabel || 'No matching staff') + '</option>';
                return;
            }
            // Keep selection only when that person is still in the filtered pool.
            const keepId = staff.some((s) => String(s.id) === String(selectedId || ''))
                ? selectedId
                : staff[0].id;
            select.innerHTML = staff.map((s) => {
                const selected = String(keepId) === String(s.id) ? ' selected' : '';
                const hint = s.position ? ' (' + s.position + ')' : '';
                return '<option value="' + s.id + '"' + selected + '>' + s.name + hint + '</option>';
            }).join('');
        }

        // Walk-in: Packages → one select per role; single services → Staff
        // dropdown filtered to employees who offer that service category.
        (function () {
            const serviceSelect = document.getElementById('walkin-service');
            const singleWrap = document.getElementById('walkin-staff-single');
            const packageWrap = document.getElementById('walkin-staff-package');
            const singleSelect = document.getElementById('walkin-personnel');
            const servicesCatalog = @json($servicesJson);
            if (!serviceSelect || !singleWrap || !packageWrap || !singleSelect) return;

            function syncWalkInStaff() {
                const service = servicesCatalog.find((s) => String(s.id) === String(serviceSelect.value));
                const isPackage = Boolean(service?.is_package && (service.positions || []).length);

                if (!isPackage) {
                    packageWrap.classList.add('d-none');
                    packageWrap.innerHTML = '';
                    singleWrap.classList.remove('d-none');
                    singleSelect.disabled = false;
                    singleSelect.required = true;
                    fillStaffSelect(
                        singleSelect,
                        staffForEligiblePositions(service?.eligible_positions || []),
                        null,
                        'No staff offer this service category'
                    );
                    return;
                }

                singleWrap.classList.add('d-none');
                singleSelect.disabled = true;
                singleSelect.required = false;
                packageWrap.classList.remove('d-none');
                packageWrap.innerHTML = '<p class="small text-muted mb-2">This package needs one staff member per role.</p>';

                (service.positions || []).forEach((position) => {
                    const wrap = document.createElement('div');
                    wrap.className = 'mb-2';
                    const options = staffCatalog
                        .filter((s) => !position.name || !s.position || s.position === position.name)
                        .map((s) => '<option value="' + s.id + '">' + s.name + '</option>')
                        .join('');
                    wrap.innerHTML =
                        '<label class="form-label">' + position.name + '</label>' +
                        '<select name="staff[' + position.id + ']" class="form-select" required>' +
                        '<option value="">Select…</option>' + options +
                        '</select>';
                    packageWrap.appendChild(wrap);
                });
            }

            serviceSelect.addEventListener('change', syncWalkInStaff);
            document.getElementById('walkInModal')?.addEventListener('show.bs.modal', syncWalkInStaff);
            syncWalkInStaff();
        })();

        // Walk-in EXTRA services: repeatable REGULAR (active, non-package)
        // rows under the main service. Each row offers the service, a variant
        // select only when that service has variants, and an optional staff
        // select filtered to the service category (disabled when the category
        // has no linked positions). Packages stay a MAIN-row concern.
        (function () {
            const wrap = document.getElementById('walkin-extras');
            const addBtn = document.getElementById('walkin-extra-add');
            const servicesCatalog = @json($servicesJson);
            if (!wrap || !addBtn) return;

            const regularServices = servicesCatalog.filter((s) => !s.is_package);
            let nextIndex = 0;

            function serviceOptions(selectedId) {
                return regularServices.map((s) => {
                    const selected = String(s.id) === String(selectedId) ? ' selected' : '';
                    return '<option value="' + s.id + '"' + selected + '>' + esc(s.name) +
                        ' — ₱' + Number(s.price).toLocaleString(undefined, { minimumFractionDigits: 2 }) + '</option>';
                }).join('');
            }

            // Resync a row's variant + staff controls to its chosen service.
            function syncRow(row) {
                const serviceSelect = row.querySelector('[data-extra-service]');
                const variantWrap = row.querySelector('[data-extra-variant-wrap]');
                const variantSelect = row.querySelector('[data-extra-variant]');
                const personnelSelect = row.querySelector('[data-extra-personnel]');
                const service = regularServices.find((s) => String(s.id) === String(serviceSelect.value));

                const variants = (service && service.variants) || [];
                if (!variants.length) {
                    variantWrap.classList.add('d-none');
                    variantSelect.innerHTML = '';
                    variantSelect.required = false;
                } else {
                    variantWrap.classList.remove('d-none');
                    variantSelect.required = true;
                    variantSelect.innerHTML = variants.map((v) =>
                        '<option value="' + esc(v.label) + '">' + esc(v.label) +
                        ' — ₱' + Number(v.price).toLocaleString(undefined, { minimumFractionDigits: 2 }) + '</option>'
                    ).join('');
                }

                const names = (service && service.eligible_positions) || [];
                if (!names.length) {
                    // No linked positions → nobody eligible; stays null.
                    personnelSelect.innerHTML = '<option value="">No eligible staff</option>';
                    personnelSelect.disabled = true;
                } else {
                    personnelSelect.disabled = false;
                    personnelSelect.innerHTML = '<option value="">No specific staff</option>' +
                        staffForEligiblePositions(names).map((s) =>
                            '<option value="' + s.id + '">' + esc(s.name) + (s.position ? ' (' + esc(s.position) + ')' : '') + '</option>'
                        ).join('');
                }
            }

            function buildRow() {
                const index = nextIndex++;
                const row = document.createElement('div');
                row.className = 'walkin-extra border rounded p-2 mb-2';
                row.setAttribute('data-extra-row', '');
                row.innerHTML =
                    '<div class="d-flex justify-content-between align-items-center mb-1">' +
                        '<span class="small text-muted">Extra service</span>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" data-extra-remove>&times;</button>' +
                    '</div>' +
                    '<div class="mb-2">' +
                        '<label class="form-label small mb-1">Service</label>' +
                        '<select name="extras[' + index + '][service_id]" class="form-select form-select-sm" data-extra-service required>' +
                            serviceOptions(regularServices[0] && regularServices[0].id) +
                        '</select>' +
                    '</div>' +
                    '<div class="mb-2 d-none" data-extra-variant-wrap>' +
                        '<label class="form-label small mb-1">Variant</label>' +
                        '<select name="extras[' + index + '][variant_label]" class="form-select form-select-sm" data-extra-variant></select>' +
                    '</div>' +
                    '<div>' +
                        '<label class="form-label small mb-1">Staff <span class="text-muted">(optional)</span></label>' +
                        '<select name="extras[' + index + '][personnel_id]" class="form-select form-select-sm" data-extra-personnel></select>' +
                    '</div>';
                syncRow(row);
                return row;
            }

            addBtn.addEventListener('click', () => {
                wrap.appendChild(buildRow());
                wrap.dispatchEvent(new Event('change', { bubbles: true }));
            });

            wrap.addEventListener('change', (event) => {
                const row = event.target.closest('[data-extra-row]');
                if (row && event.target.matches('[data-extra-service]')) {
                    syncRow(row);
                }
            });

            wrap.addEventListener('click', (event) => {
                const removeBtn = event.target.closest('[data-extra-remove]');
                if (!removeBtn) return;
                removeBtn.closest('[data-extra-row]').remove();
                wrap.dispatchEvent(new Event('change', { bubbles: true }));
            });
        })();

        // Walk-in ONLY: warn BEFORE submit when the start would finish after
        // the configured walk-in close (default 01:00 next morning). Mirrors
        // App\Support\WalkInHours — not BusinessHours::startFitsOnDate(),
        // which remains the online booking rule.
        (function () {
            const dateInput = document.getElementById('walkin-date');
            const timeInput = document.getElementById('walkin-time');
            const serviceSelect = document.getElementById('walkin-service');
            const submitBtn = document.getElementById('walkin-submit');
            const hint = document.getElementById('walkin-time-hint');
            if (!dateInput || !timeInput || !serviceSelect || !submitBtn || !hint) return;

            const nxBusinessHours = @json($businessHoursJson);
            const nxServices = @json($servicesJson);
            const nxWalkInCloseAt = @json($walkInCloseAt);

            function minutesOf(hhmm) {
                const parts = String(hhmm).split(':');
                return (parseInt(parts[0], 10) || 0) * 60 + (parseInt(parts[1], 10) || 0);
            }

            // Minutes since midnight (may exceed 1440 for the overnight close)
            // to 12-hour "g:i A" — e.g. 1530 → "1:30 AM", 1500 → "1:00 AM".
            function fmt(minutes) {
                const total = ((minutes % 1440) + 1440) % 1440;
                const hour24 = Math.floor(total / 60);
                const meridiem = hour24 < 12 ? 'AM' : 'PM';
                const hour12 = hour24 % 12 === 0 ? 12 : hour24 % 12;
                return hour12 + ':' + String(total % 60).padStart(2, '0') + ' ' + meridiem;
            }

            function setInvalid(message) {
                hint.textContent = message;
                hint.classList.remove('d-none');
                timeInput.classList.add('is-invalid');
                submitBtn.disabled = true;
            }

            function setNeutral() {
                hint.textContent = '';
                hint.classList.add('d-none');
                timeInput.classList.remove('is-invalid');
                submitBtn.disabled = false;
            }

            // Minutes added by every extra-service row chosen on the visit;
            // the closing check judges main + extras together.
            function extraDurationMinutes() {
                const container = document.getElementById('walkin-extras');
                if (!container) return 0;
                let total = 0;
                container.querySelectorAll('[data-extra-service]').forEach((select) => {
                    const extra = nxServices.find((s) => String(s.id) === String(select.value));
                    total += Number((extra && extra.duration_minutes) || 0);
                });
                return total;
            }

            function validateWalkinTime() {
                // Neutral until there is a date, time and service to judge —
                // the inputs' own `required` attributes handle the empties.
                if (!dateInput.value || !timeInput.value || !serviceSelect.value) {
                    setNeutral();
                    return;
                }

                const service = nxServices.find((s) => String(s.id) === String(serviceSelect.value));
                const duration = Number((service && service.duration_minutes) || 0) + extraDurationMinutes();

                // Parse YYYY-MM-DD by hand — never new Date(value), whose
                // timezone behavior can shift the weekday.
                const weekday = new Date(
                    parseInt(dateInput.value.slice(0, 4), 10),
                    parseInt(dateInput.value.slice(5, 7), 10) - 1,
                    parseInt(dateInput.value.slice(8, 10), 10)
                ).getDay();

                const hours = nxBusinessHours[weekday];
                if (!hours || !hours.is_open || !hours.open) {
                    setInvalid('The shop is closed on that day. Please choose another day.');
                    return;
                }

                const openMin = minutesOf(hours.open);
                const closeMin = minutesOf(nxWalkInCloseAt);
                const effectiveClose = closeMin <= openMin ? closeMin + 1440 : closeMin;
                let startMin = minutesOf(timeInput.value);
                // Overnight extension: 00:00–(close-1) maps past midnight.
                if (effectiveClose > 1440 && startMin < closeMin) {
                    startMin += 1440;
                }

                if (startMin < openMin) {
                    setInvalid('That time is before shop opening. Please choose a later slot.');
                    return;
                }
                if ((startMin + duration) > effectiveClose) {
                    const endMin = startMin + duration;
                    setInvalid("That slot won't fit — this service would end at " + fmt(endMin)
                        + ", past the " + fmt(effectiveClose)
                        + " walk-in closing. Please choose an earlier slot, or book for another day.");
                    return;
                }

                setNeutral();
            }

            document.getElementById('walkInModal')?.addEventListener('shown.bs.modal', validateWalkinTime);
            serviceSelect.addEventListener('change', validateWalkinTime);
            // Adding / removing / switching an extra row also changes the
            // visit's total duration — the extras block bubbles a change event.
            document.getElementById('walkin-extras')?.addEventListener('change', validateWalkinTime);
            dateInput.addEventListener('change', validateWalkinTime);
            timeInput.addEventListener('change', validateWalkinTime);
            timeInput.addEventListener('input', validateWalkinTime);
        })();

        function esc(s) { return (s == null ? '' : String(s)).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

        function openDrawer(event) {
            const p = event.extendedProps;
            document.getElementById('dw-initials').textContent = (p.client || '?').trim().charAt(0).toUpperCase();
            document.getElementById('dw-client').textContent = p.client || '—';
            document.getElementById('dw-refcode').textContent = p.reference_code || '—';
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
            document.getElementById('dw-status').textContent =
                p.pending_cancellation ? 'Pending — cancellation requested' : (p.statusLabel || '—');

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
            document.getElementById('dw-paystate').textContent = p.paymentState || '';

            // Locked bookings (Unverified + pending payment) get no status
            // buttons at all — just the Payments hint above. Staff assignment
            // stays available so "no preference" bookings can be staffed.
            document.getElementById('dw-verify-hint').classList.toggle('d-none', !p.verificationLocked);

            // Pending cancellation replaces the operation buttons with the
            // notice panel above (status badge already swapped above).
            const cancelNotice = document.getElementById('dw-cancel-notice');
            cancelNotice.classList.toggle('d-none', !p.pending_cancellation);
            const reasonWrap = document.getElementById('dw-cancel-reason-wrap');
            if (p.pending_cancellation && p.cancellation_reason) {
                document.getElementById('dw-cancel-reason').textContent = p.cancellation_reason;
                reasonWrap.classList.remove('d-none');
            } else {
                reasonWrap.classList.add('d-none');
            }

            const notesRow = document.getElementById('dw-notes-row');
            if (p.notes) { notesRow.classList.remove('d-none'); document.getElementById('dw-notes').textContent = p.notes; }
            else { notesRow.classList.add('d-none'); }

            // Avail services: creation-time picks read as part of the
            // appointment's services (under Service above); services added
            // later from Payments are the separate "Additional services"
            // row, hidden until at least one exists. Both are read-only
            // here; Remove stays available while the appointment can still
            // be modified (Booked / In-Service).
            const availedList = document.getElementById('dw-availed-list');
            const addedList = document.getElementById('dw-added-list');
            const addedRow = document.getElementById('dw-added-row');
            const addedRows = p.addedServices || [];
            // Original avail: creation-time walk-in picks AND extras chosen
            // inside the client's online booking wizard ('booking'). Only
            // later payment-time adds are the separate "Additional services"
            // line.
            const creationRows = addedRows.filter((row) => (row.source || 'payment') !== 'payment');
            const paymentRows = addedRows.filter((row) => (row.source || 'payment') === 'payment');

            function addedLine(row) {
                const line = document.createElement('div');
                line.className = 'd-flex justify-content-between align-items-start gap-2';
                const label = row.service + (row.variant ? ' (' + row.variant + ')' : '');
                const meta = [row.personnel || '—', row.start_time_label || ''].filter(Boolean).join(' · ');
                line.innerHTML =
                    '<span>' + esc(label) +
                    ' · ₱' + Number(row.price).toLocaleString(undefined, { minimumFractionDigits: 2 }) +
                    '<br><span class="text-muted">' + esc(meta) + '</span></span>';
                if (p.canAddServices) {
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn btn-sm btn-outline-danger py-0 px-1';
                    removeBtn.textContent = 'Remove';
                    removeBtn.addEventListener('click', () => {
                        if (!confirm('Remove ' + label + ' from this appointment?')) return;
                        const form = document.getElementById('dw-removeServiceForm');
                        form.action = statusBase + '/' + event.id + '/additional-services/' + row.id;
                        form.submit();
                    });
                    line.appendChild(removeBtn);
                }
                return line;
            }

            availedList.innerHTML = '';
            creationRows.forEach((row) => availedList.appendChild(addedLine(row)));

            addedList.innerHTML = '';
            paymentRows.forEach((row) => addedList.appendChild(addedLine(row)));
            addedRow.classList.toggle('d-none', paymentRows.length === 0);

            // No-personnel warning: only when nobody is assigned and the
            // appointment is still actionable (not terminal).
            document.getElementById('dw-staff-notice').classList.toggle('d-none', !(!p.personnelId && !p.isTerminal));

            function confirmSummary() {
                return (p.client || '—') + ' — ' + (p.service || '—') + ' — '
                    + (p.dateLabel || '') + (p.timeLabel ? ' · ' + p.timeLabel : '');
            }

            const form = document.getElementById('dw-statusForm');
            form.action = statusBase + '/' + event.id + '/status';
            const actions = document.getElementById('dw-actions');
            actions.innerHTML = '';
            let hasAction = false;
            (p.actions || []).forEach(a => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-' + a.style;
                btn.textContent = a.label;
                if (a.to === 'no-show') {
                    // Confirmation modal first — a plain click submits nothing.
                    btn.addEventListener('click', () => {
                        document.getElementById('dw-noshow-summary').textContent = confirmSummary();
                        document.getElementById('dw-status-target').value = a.to;
                        new bootstrap.Modal(document.getElementById('dwNoShowModal')).show();
                    });
                } else if (a.to === 'cancelled') {
                    // Confirmation modal first (with optional reason) — a
                    // plain click submits nothing.
                    btn.addEventListener('click', () => {
                        document.getElementById('dw-cancel-summary').textContent = confirmSummary();
                        document.getElementById('dw-cancel-reason-input').value = '';
                        document.getElementById('dw-status-target').value = a.to;
                        new bootstrap.Modal(document.getElementById('dwCancelModal')).show();
                    });
                } else {
                    btn.addEventListener('click', () => {
                        document.getElementById('dw-status-target').value = a.to;
                        form.submit();
                    });
                }
                actions.appendChild(btn);
                hasAction = true;
            });
            if (!p.isTerminal && !p.pending_cancellation) {
                const assignBtn = document.createElement('button');
                assignBtn.type = 'button';
                assignBtn.className = 'btn btn-outline-secondary';
                assignBtn.textContent = 'Assign Staff';
                assignBtn.addEventListener('click', () => {
                    document.getElementById('assignForm').action = statusBase + '/' + event.id + '/assign-personnel';
                    const singleWrap = document.getElementById('assign-single-wrap');
                    const packageWrap = document.getElementById('assign-package-wrap');
                    const singleSelect = document.getElementById('assign-personnel');

                    if (p.isPackage && (p.requiredPositions || []).length) {
                        singleWrap.classList.add('d-none');
                        singleSelect.required = false;
                        singleSelect.disabled = true;
                        packageWrap.classList.remove('d-none');
                        packageWrap.innerHTML = '';
                        const assigned = {};
                        (p.packageStaff || []).forEach((row) => { assigned[row.position_id] = row.user_id; });
                        (p.requiredPositions || []).forEach((position) => {
                            const wrap = document.createElement('div');
                            wrap.className = 'mb-2';
                            const options = staffCatalog
                                .filter((s) => !position.name || !s.position || s.position === position.name)
                                .map((s) => '<option value="' + s.id + '"' + (String(assigned[position.id] || '') === String(s.id) ? ' selected' : '') + '>' + s.name + ' (' + (s.position || 'Staff') + ')</option>')
                                .join('');
                            wrap.innerHTML =
                                '<label class="form-label">' + position.name + '</label>' +
                                '<select name="staff[' + position.id + ']" class="form-select" required>' +
                                '<option value="">Select…</option>' + options +
                                '</select>';
                            packageWrap.appendChild(wrap);
                        });
                    } else {
                        packageWrap.classList.add('d-none');
                        packageWrap.innerHTML = '';
                        singleWrap.classList.remove('d-none');
                        singleSelect.disabled = false;
                        singleSelect.required = true;
                        fillStaffSelect(
                            singleSelect,
                            staffForEligiblePositions(p.eligiblePositions || []),
                            p.personnelId,
                            'No staff offer this service category'
                        );
                    }
                    new bootstrap.Modal(document.getElementById('assignModal')).show();
                });
                actions.appendChild(assignBtn);
                hasAction = true;
            }
            if (!hasAction && !p.verificationLocked && !p.pending_cancellation) {
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

        // Drawer confirmation modals: only Confirm submits the shared
        // status form (to the existing endpoint); Cancel dismisses.
        document.getElementById('dw-noshow-confirm').addEventListener('click', () => {
            document.getElementById('dw-reason').value = '';
            document.getElementById('dw-statusForm').submit();
        });
        document.getElementById('dw-cancel-confirm').addEventListener('click', () => {
            document.getElementById('dw-reason').value = document.getElementById('dw-cancel-reason-input').value;
            document.getElementById('dw-statusForm').submit();
        });

        const overrideModal = document.getElementById('overrideModal');
        overrideModal.addEventListener('show.bs.modal', (event) => {
            const btn = event.relatedTarget;
            document.getElementById('overrideForm').action = statusBase + '/' + btn.dataset.id + '/override';
            document.getElementById('ov-date').value = btn.dataset.date;
            document.getElementById('ov-time').value = btn.dataset.time;
            let eligible = [];
            try { eligible = JSON.parse(btn.dataset.eligiblePositions || '[]'); } catch (e) { eligible = []; }
            fillStaffSelect(
                document.getElementById('ov-personnel'),
                staffForEligiblePositions(eligible),
                btn.dataset.personnel,
                'No staff offer this service category'
            );
        });
    });
</script>
@endpush
