@extends('layouts.staff')

@section('title', 'My Schedule')

@push('head')
<style>
    /* Personal calendar — same visual language as the admin Appointments
       calendar (AppointmentController@feed colours + .nx-evt-* dark-mode
       overrides), read-only here. */
    .fc .fc-event.nx-evt {
        padding: 1px 5px; font-size: .78rem; font-weight: 500;
        border-radius: 5px; border-width: 1px; cursor: pointer;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        max-width: 100%; display: block;
    }
    .fc .fc-event.nx-evt .fc-event-time { font-weight: 700; white-space: nowrap; }
    .fc .fc-event.nx-evt-leave { cursor: default; }

    /* Grid follows the staff theme in both modes. */
    .fc { --fc-border-color: var(--nex-border); --fc-today-bg-color: rgba(201, 162, 75, .14); }
    .fc .fc-toolbar-title,
    .fc .fc-col-header-cell-cushion,
    .fc .fc-daygrid-day-number,
    .fc .fc-list-day-text { color: var(--nex-text); }

    html[data-bs-theme="dark"] .fc {
        --fc-page-bg-color: transparent;
        --fc-neutral-bg-color: #1c150f;
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

    .nx-legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .nx-legend-band { width: 14px; height: 10px; border-radius: 2px; display: inline-block; background: rgba(201, 162, 75, .35); }
    .nx-detail-row { display: flex; gap: .75rem; padding: .6rem 0; border-bottom: 1px solid var(--nex-border); }
    .nx-detail-label { color: var(--nex-muted); font-size: .8rem; min-width: 110px; }
</style>
@endpush

@section('content')
<div class="mb-4">
    <h1 class="h3 mb-1">My Schedule</h1>
    <p class="text-muted mb-0">{{ $staff->fullName() }} · {{ $staff->staffProfile?->position ?? 'Staff' }}</p>
</div>

<div class="card p-3 mb-3">
    <div class="d-flex flex-wrap gap-3 small mb-3">
        <span class="d-inline-flex align-items-center gap-1"><span class="nx-legend-dot" style="background:#fcebd2; border:1px solid #ecc78f;"></span>Pending verification</span>
        <span class="d-inline-flex align-items-center gap-1"><span class="nx-legend-dot" style="background:#dcf0e2; border:1px solid #a9d9bc;"></span>Booked</span>
        <span class="d-inline-flex align-items-center gap-1"><span class="nx-legend-dot" style="background:#deeafe; border:1px solid #aec8f7;"></span>In service</span>
        <span class="d-inline-flex align-items-center gap-1"><span class="nx-legend-dot" style="background:#eae1f6; border:1px solid #cdb8ec;"></span>Completed</span>
        <span class="d-inline-flex align-items-center gap-1"><span class="nx-legend-band"></span>Shift</span>
        <span class="d-inline-flex align-items-center gap-1"><span class="nx-legend-dot" style="background:#ececec; border:1px solid #d9d9d9;"></span>On leave</span>
    </div>
    <div id="staffCalendar"></div>
</div>

{{-- READ-ONLY appointment details — no action buttons; completing services stays on the dashboard. --}}
<div class="modal fade" id="apptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="nx-detail-row"><span class="nx-detail-label">Appointment ID</span><strong id="appt-refcode">—</strong></div>
                <div class="nx-detail-row"><span class="nx-detail-label">Client</span><span id="appt-client">—</span></div>
                <div class="nx-detail-row"><span class="nx-detail-label">Service</span><span id="appt-service">—</span></div>
                <div class="nx-detail-row"><span class="nx-detail-label">Date</span><span id="appt-date">—</span></div>
                <div class="nx-detail-row"><span class="nx-detail-label">Time</span><span id="appt-time">—</span></div>
                <div class="nx-detail-row" style="border-bottom:0;"><span class="nx-detail-label">Status</span><span id="appt-status">—</span></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = new bootstrap.Modal(document.getElementById('apptModal'));

        const calendar = new FullCalendar.Calendar(document.getElementById('staffCalendar'), {
            initialView: 'timeGridWeek',
            firstDay: 0,
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
            events: "{{ route('staff.schedule.events') }}",
            slotMinTime: '08:00:00',
            slotMaxTime: '22:00:00',
            eventClick: (info) => {
                if (info.event.extendedProps.kind !== 'appointment') return;
                const p = info.event.extendedProps;
                document.getElementById('appt-refcode').textContent = p.reference_code || '—';
                document.getElementById('appt-client').textContent = p.client || '—';
                document.getElementById('appt-service').textContent = p.service || '—';
                document.getElementById('appt-date').textContent = p.dateLabel || '—';
                document.getElementById('appt-time').textContent = p.timeLabel || '—';
                document.getElementById('appt-status').textContent = p.statusLabel || '—';
                modal.show();
            },
        });
        calendar.render();
    });
</script>
@endpush
