{{-- Client "Appointments" hub (nav label "Appointments" — see
     partials/nav.blade.php; route name stays account.bookings). Behind
     ['auth', 'role:4'] — see routes/web.php. $pending/$upcoming/$history
     are the signed-in client's own Appointment rows only
     (ClientAccountController@bookings filters by user_id = auth()->id()),
     grouped by the existing AppointmentStatus enum values — no new status
     is invented for this UI.

     Cancellation / reschedule: the client can only *request* a change here
     (App\Models\AppointmentChangeRequest) — a Manager approves it in the
     /admin queue before anything on the appointment actually moves. One
     pending request per appointment at a time. --}}
@extends('layouts.public')

@section('title', 'My Bookings')

@section('content')
<header class="nx-hero">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
            <div>
                <p class="nx-eyebrow mb-2">My Account</p>
                <h1 class="mb-0">Appointments</h1>
            </div>
            <a href="{{ route('account.booking.start') }}" class="nx-btn nx-btn-primary"
               data-bs-toggle="modal" data-bs-target="#bookServiceModal">
                <i class="bi bi-calendar-plus me-1"></i>Book Appointment
            </a>
        </div>
    </div>
</header>

<section class="nx-section" style="background: var(--nx-bg-page); padding-top: 48px;">
    <div class="container">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <ul class="nav nav-pills mb-4 gap-2 flex-wrap" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link nx-pill active" data-bs-toggle="pill" data-bs-target="#bk-upcoming" type="button" role="tab">
                    Upcoming <span class="opacity-75">({{ $upcoming->count() }})</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link nx-pill" data-bs-toggle="pill" data-bs-target="#bk-pending" type="button" role="tab">
                    Pending <span class="opacity-75">({{ $pending->count() }})</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link nx-pill" data-bs-toggle="pill" data-bs-target="#bk-history" type="button" role="tab">
                    History <span class="opacity-75">({{ $history->count() }})</span>
                </button>
            </li>
        </ul>

        <div class="tab-content">
            @foreach(['bk-upcoming' => $upcoming, 'bk-pending' => $pending, 'bk-history' => $history] as $paneId => $list)
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $paneId }}" role="tabpanel">
                    @if($list->isEmpty())
                        <div class="nx-card p-4 text-center">
                            <p class="nx-text-secondary mb-0">Nothing here yet.</p>
                        </div>
                    @else
                        <div class="d-flex flex-column gap-3">
                            @foreach($list as $appointment)
                                @php($statusClass = match($appointment->status->value) {
                                    'unverified' => 'nx-status-pending',
                                    'booked', 'in-service' => 'nx-status-upcoming',
                                    'cancelled', 'no-show' => 'nx-status-cancelled',
                                    default => 'nx-status-done',
                                })
                                @php($canRequestChange = in_array($appointment->status->value, ['unverified', 'booked'], true))
                                @php($pendingRequest = $appointment->pendingChangeRequest)
                                <div class="nx-booking-row">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                        <h3 class="h6 mb-0">
                                            {{ $appointment->service->name ?? 'Service' }}
                                            @if($appointment->address)
                                                <span class="nx-badge ms-1"><i class="bi bi-house-heart me-1"></i>Home Service</span>
                                            @endif
                                        </h3>
                                        <span class="nx-status-badge {{ $statusClass }}">{{ $appointment->status->label() }}</span>
                                    </div>
                                    <p class="small nx-text-secondary mb-1">
                                        <i class="bi bi-calendar3 me-2"></i>{{ $appointment->appointment_date->format('F j, Y') }}
                                        &middot; {{ \Illuminate\Support\Carbon::parse($appointment->start_time)->format('g:i A') }}
                                    </p>
                                    @if($appointment->personnel)
                                        <p class="small nx-text-secondary mb-0"><i class="bi bi-person me-2"></i>{{ $appointment->personnel->name }}</p>
                                    @endif
                                    @if($appointment->address)
                                        <p class="small nx-text-secondary mb-0"><i class="bi bi-geo-alt me-2"></i>{{ $appointment->address->formatted() }}</p>
                                    @endif

                                    {{-- Change-request area --}}
                                    @if($pendingRequest)
                                        <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 mb-0 py-2 px-3">
                                            <span class="small">
                                                <i class="bi bi-hourglass-split me-1"></i>
                                                @if($pendingRequest->type === \App\Models\AppointmentChangeRequest::TYPE_RESCHEDULE)
                                                    Reschedule requested for
                                                    <strong>{{ \Illuminate\Support\Carbon::parse($pendingRequest->requested_date)->format('M j, Y') }}
                                                    at {{ \Illuminate\Support\Carbon::parse($pendingRequest->requested_start_time)->format('g:i A') }}</strong>@if($pendingRequest->requestedPersonnel) with {{ $pendingRequest->requestedPersonnel->name }}@endif — pending review.
                                                @else
                                                    Cancellation requested — pending review.
                                                @endif
                                            </span>
                                            <form method="POST"
                                                  action="{{ route('account.bookings.change-request.withdraw', [$appointment->id, $pendingRequest->id]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-secondary">Withdraw</button>
                                            </form>
                                        </div>
                                    @elseif($canRequestChange)
                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                            <button type="button" class="nx-btn nx-btn-outline btn-sm js-change-request"
                                                    data-bs-toggle="modal" data-bs-target="#changeRequestModal"
                                                    data-appointment="{{ $appointment->id }}"
                                                    data-service="{{ $appointment->service->name ?? 'Service' }}"
                                                    data-current="{{ $appointment->appointment_date->format('M j, Y') }} at {{ \Illuminate\Support\Carbon::parse($appointment->start_time)->format('g:i A') }}"
                                                    data-type="reschedule">
                                                <i class="bi bi-calendar-event me-1"></i>Request reschedule
                                            </button>
                                            <button type="button" class="nx-btn nx-btn-outline btn-sm js-change-request"
                                                    data-bs-toggle="modal" data-bs-target="#changeRequestModal"
                                                    data-appointment="{{ $appointment->id }}"
                                                    data-service="{{ $appointment->service->name ?? 'Service' }}"
                                                    data-current="{{ $appointment->appointment_date->format('M j, Y') }} at {{ \Illuminate\Support\Carbon::parse($appointment->start_time)->format('g:i A') }}"
                                                    data-type="cancellation">
                                                <i class="bi bi-x-circle me-1"></i>Request cancellation
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- SHARED CHANGE-REQUEST MODAL --}}
<div class="modal fade" id="changeRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="changeRequestForm" class="modal-content nx-body" style="border-radius: var(--nx-radius-lg); overflow: hidden;">
            @csrf
            <input type="hidden" name="type" id="cr-type">
            <div class="modal-header" style="border-color: var(--nx-border);">
                <h2 class="h5 mb-0" id="cr-title">Request a change</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small nx-text-secondary" id="cr-context"></p>

                <div class="btn-group w-100 mb-3" role="group">
                    <button type="button" class="btn btn-outline-secondary js-cr-mode" data-mode="reschedule">Reschedule</button>
                    <button type="button" class="btn btn-outline-secondary js-cr-mode" data-mode="cancellation">Cancel appointment</button>
                </div>

                <div id="cr-reschedule-fields">
                    <div class="row g-2">
                        <div class="col-sm-6 mb-2">
                            <label class="form-label small">Preferred new date</label>
                            <input type="date" name="requested_date" id="cr-date" class="form-control" min="{{ now()->addDay()->toDateString() }}">
                        </div>
                        <div class="col-sm-6 mb-2">
                            <label class="form-label small">Preferred new time</label>
                            <input type="time" name="requested_start_time" id="cr-time" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Preferred staff <span class="nx-text-secondary">(optional)</span></label>
                        <select name="requested_personnel_id" class="form-select">
                            <option value="">No preference</option>
                            @foreach($personnel as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label small">Message to the team <span class="nx-text-secondary">(optional)</span></label>
                    <textarea name="reason" rows="3" maxlength="1000" class="form-control" placeholder="Let us know why, or anything else that helps."></textarea>
                </div>
                <p class="small nx-text-secondary mb-0">
                    <i class="bi bi-info-circle me-1"></i>Nothing changes yet — the team reviews every request first and you'll be notified of the outcome.
                </p>
            </div>
            <div class="modal-footer" style="border-color: var(--nx-border);">
                <button type="button" class="nx-btn nx-btn-outline" data-bs-dismiss="modal">Never mind</button>
                <button class="nx-btn nx-btn-primary" id="cr-submit">Send request</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        var modal = document.getElementById('changeRequestModal');
        if (!modal) return;

        var form = document.getElementById('changeRequestForm');
        var typeInput = document.getElementById('cr-type');
        var title = document.getElementById('cr-title');
        var context = document.getElementById('cr-context');
        var rescheduleFields = document.getElementById('cr-reschedule-fields');
        var dateInput = document.getElementById('cr-date');
        var timeInput = document.getElementById('cr-time');
        var submitBtn = document.getElementById('cr-submit');
        var base = "{{ url('account/bookings') }}";

        function setMode(mode) {
            typeInput.value = mode;
            var isReschedule = mode === 'reschedule';
            rescheduleFields.hidden = !isReschedule;
            dateInput.required = isReschedule;
            timeInput.required = isReschedule;
            title.textContent = isReschedule ? 'Request a reschedule' : 'Request a cancellation';
            submitBtn.textContent = isReschedule ? 'Send reschedule request' : 'Send cancellation request';
            modal.querySelectorAll('.js-cr-mode').forEach(function (b) {
                b.classList.toggle('active', b.dataset.mode === mode);
                b.classList.toggle('btn-secondary', b.dataset.mode === mode);
                b.classList.toggle('btn-outline-secondary', b.dataset.mode !== mode);
            });
        }

        modal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;
            form.action = base + '/' + btn.dataset.appointment + '/change-requests';
            context.textContent = btn.dataset.service + ' — currently ' + btn.dataset.current;
            form.reset();
            setMode(btn.dataset.type || 'reschedule');
        });

        modal.querySelectorAll('.js-cr-mode').forEach(function (b) {
            b.addEventListener('click', function () { setMode(b.dataset.mode); });
        });
    })();
</script>
@endpush
@endsection
