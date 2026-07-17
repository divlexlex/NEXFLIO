@extends('layouts.admin')

@section('title', 'Appointments')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Appointments</h1>
    <button class="btn btn-spa" data-bs-toggle="modal" data-bs-target="#walkInModal">
        <i class="bi bi-person-plus me-1"></i>Walk-in Entry
    </button>
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
                        <td>{{ $appointment->personnel->name ?? '—' }}</td>
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
                    Reschedule or reassign. The change is recorded in the audit trail and the
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
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
            initialView: 'dayGridMonth',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
            events: "{{ route('admin.appointments.feed') }}",
            height: 620,
        });
        calendar.render();

        const overrideModal = document.getElementById('overrideModal');
        overrideModal.addEventListener('show.bs.modal', (event) => {
            const btn = event.relatedTarget;
            document.getElementById('overrideForm').action =
                "{{ url('admin/appointments') }}/" + btn.dataset.id + "/override";
            document.getElementById('ov-date').value = btn.dataset.date;
            document.getElementById('ov-time').value = btn.dataset.time;
            document.getElementById('ov-personnel').value = btn.dataset.personnel;
        });
    });
</script>
@endpush
