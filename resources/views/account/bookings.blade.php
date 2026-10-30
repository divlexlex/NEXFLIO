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
                                @php($canRequestChange = $appointment->status === \App\Enums\AppointmentStatus::Booked)
                                @php($pendingRequest = $appointment->pendingChangeRequest)
                                <div class="nx-booking-row">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                                        <h3 class="h6 mb-0">
                                            {{ $appointment->displayServiceName() }}
                                            @if($appointment->address)
                                                <span class="nx-badge ms-1"><i class="bi bi-house-heart me-1"></i>Home Service</span>
                                            @endif
                                        </h3>
                                        <span class="nx-status-badge {{ $statusClass }}">{{ $appointment->status->label() }}</span>
                                    </div>
                                    @if($appointment->addedServices->isNotEmpty())
                                        <p class="small nx-text-secondary mb-1">
                                            <i class="bi bi-plus-circle me-2"></i>{{ $appointment->addedServices->map(fn ($added) => $added->displayName() . ($added->personnel ? ' with ' . $added->personnel->name : '') . ($added->startTimeLabel() ? ' at ' . $added->startTimeLabel() : ''))->implode(', ') }}
                                        </p>
                                    @endif
                                    <p class="small nx-text-secondary mb-1">
                                        <i class="bi bi-calendar3 me-2"></i>{{ $appointment->appointment_date->format('F j, Y') }}
                                        &middot; {{ \Illuminate\Support\Carbon::parse($appointment->start_time)->format('g:i A') }}
                                    </p>
                                    @if($appointment->price !== null)
                                        <p class="small nx-text-secondary mb-1"><i class="bi bi-cash-coin me-2"></i>₱{{ number_format((float) $appointment->price, 2)}}</p>
                                        <p class="small nx-text-secondary mb-1"><i class="bi bi-wallet2 me-2"></i>{{ $appointment->paymentState() }}</p>
                                        @php($showContinueButton = false)
                                        @php($showResubmitButton = false)
                                        @if($appointment->payment?->status->value === 'rejected')
                                            <p class="small nx-text-secondary mb-1"><i class="bi bi-x-circle me-2"></i>Payment rejected{{ $appointment->payment->rejectionCategoryLabel() ? ' — '.$appointment->payment->rejectionCategoryLabel() : '' }}{{ $appointment->payment->rejection_reason ? ': '.$appointment->payment->rejection_reason : '' }}</p>
                                            {{-- Rejected-payment outcomes: "Continue to book this"
                                                 (top-up) XOR "Send a new proof" (resubmit) — never
                                                 both, never a refund. Anything else is final with
                                                 no action buttons. The action row itself lives at
                                                 the very bottom of the card, sharing one line
                                                 with the ID and personnel lines below. --}}
                                            @php($showContinueButton = $appointment->canContinueBooking())
                                            @php($showResubmitButton = $appointment->canResubmitProof())
                                            @if($showResubmitButton)
                                                <p class="small nx-text-secondary mb-1"><i class="bi bi-info-circle me-2"></i>Your proof was rejected as invalid or unreadable — you can send a clearer photo or screenshot.</p>
                                            @endif
                                        @endif
                                        {{-- Cancelled-card refund lines (approved cancellation
                                             request's refund_decision + primary payment's
                                             refund_status; X = refundDueAmount(), the same
                                             helper the admin card uses). Nothing paid → no line. --}}
                                        @php($approvedCancellation = ($appointment->relationLoaded('changeRequests') ? $appointment->changeRequests : $appointment->changeRequests()->get())->sortByDesc('id')->first())
                                        @if($appointment->status === \App\Enums\AppointmentStatus::Cancelled && $approvedCancellation && $approvedCancellation->refund_decision)
                                            @php($refundX = $appointment->payment ? $appointment->payment->refundDueAmount() : $appointment->verifiedPaid())
                                            @if($refundX > 0)
                                                @if($approvedCancellation->refund_decision === 'refundable' && $appointment->payment?->refund_status === 'requested')
                                                    <p class="small nx-text-secondary mb-1"><i class="bi bi-arrow-counterclockwise me-2"></i>Refund expected: ₱{{ number_format($refundX, 2) }} — the branch will send it back to your payment method.</p>
                                                @elseif($approvedCancellation->refund_decision === 'refundable' && $appointment->payment?->refund_status === 'sent')
                                                    <p class="small nx-text-secondary mb-1"><i class="bi bi-check-circle me-2"></i>Refund sent: ₱{{ number_format($refundX, 2) }} · {{ $appointment->payment->refund_sent_at?->format('M j, Y') }}</p>
                                                @elseif($approvedCancellation->refund_decision === 'non_refundable')
                                                    <p class="small nx-text-secondary mb-1"><i class="bi bi-info-circle me-2"></i>Down payment is non-refundable (per booking policy).</p>
                                                @endif
                                            @endif
                                        @endif
                                        @if($showContinueButton || $showResubmitButton)
                                            {{-- Bottom line of the card: ID + personnel stay
                                                 bottom-left, action button(s) sit bottom-right
                                                 in identical solid styling — text is the only
                                                 difference. No top margin beyond the card's
                                                 normal line spacing. --}}
                                            <div class="d-flex justify-content-between align-items-end flex-wrap gap-2">
                                                <div>
                                                    <p class="small nx-text-secondary mb-1"><i class="bi bi-hash me-2"></i>ID {{ $appointment->referenceCode() }}</p>
                                                    @if($appointment->personnel)
                                                        <p class="small nx-text-secondary mb-0"><i class="bi bi-person me-2"></i>{{ $appointment->personnel->name }}</p>
                                                    @endif
                                                </div>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    @if($showContinueButton)
                                                        <a href="{{ route('account.bookings.topup', $appointment->id) }}" class="nx-btn nx-btn-primary btn-sm">Continue to book this — add ₱{{ number_format($appointment->shortfall(), 2) }}</a>
                                                    @endif
                                                    @if($showResubmitButton)
                                                        <button type="button" class="nx-btn nx-btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#resubmit-{{ $appointment->id }}" aria-expanded="false">
                                                            <i class="bi bi-upload me-1"></i>Send a new proof
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                            @if($showResubmitButton)
                                                <div class="collapse mt-2" id="resubmit-{{ $appointment->id }}">
                                                    <form method="POST" action="{{ route('account.bookings.resubmit-proof', $appointment->id) }}" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                                                        @csrf
                                                        <input type="file" name="payment_proof" class="form-control form-control-sm resubmit-proof-input" accept="image/*" required>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <button type="button" class="nx-preview-link d-none" data-preview-btn>
                                                                <i class="bi bi-eye me-1"></i>View image
                                                            </button>
                                                            <button type="submit" class="nx-btn nx-btn-primary btn-sm">Submit proof</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            @endif
                                        @else
                                            <p class="small nx-text-secondary mb-1"><i class="bi bi-hash me-2"></i>ID {{ $appointment->referenceCode() }}</p>
                                        @endif
                                    @endif
                                    @if($appointment->personnel && ! ($showContinueButton || $showResubmitButton))
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
                                                    data-service="{{ $appointment->displayServiceName() }}"
                                                    data-current="{{ $appointment->appointment_date->format('M j, Y') }} at {{ \Illuminate\Support\Carbon::parse($appointment->start_time)->format('g:i A') }}"
                                                    data-type="reschedule">
                                                <i class="bi bi-calendar-event me-1"></i>Request reschedule
                                            </button>
                                            <button type="button" class="nx-btn nx-btn-outline btn-sm js-change-request"
                                                    data-bs-toggle="modal" data-bs-target="#changeRequestModal"
                                                    data-appointment="{{ $appointment->id }}"
                                                    data-service="{{ $appointment->displayServiceName() }}"
                                                    data-current="{{ $appointment->appointment_date->format('M j, Y') }} at {{ \Illuminate\Support\Carbon::parse($appointment->start_time)->format('g:i A') }}"
                                                    data-type="cancellation">
                                                <i class="bi bi-x-circle me-1"></i>Request cancellation
                                            </button>
                                        </div>
                                    @elseif($appointment->status === \App\Enums\AppointmentStatus::Unverified)
                                        <p class="small nx-text-secondary mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Requests to reschedule or cancel will be available once your payment is verified.</p>
                                    @endif

                                    {{-- Feedback (Completed cards only — refund/continue buttons above are untouched) --}}
                                    @if($appointment->status === \App\Enums\AppointmentStatus::Completed)
                                        @php($review = $appointment->review)
                                        <div class="mt-3 pt-3 border-top">
                                            @if($review)
                                                <p class="small fw-semibold mb-1">Your feedback</p>
                                                @if($review->comment)
                                                    <p class="small nx-text-secondary mb-2 fst-italic">“{{ $review->comment }}”</p>
                                                @else
                                                    <p class="small nx-text-secondary mb-2">You haven't written feedback yet.</p>
                                                @endif
                                                <button type="button" class="nx-btn nx-btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal-{{ $appointment->id }}">
                                                    <i class="bi bi-pencil me-1"></i>Edit feedback
                                                </button>
                                            @else
                                                <button type="button" class="nx-btn nx-btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal-{{ $appointment->id }}">
                                                    <i class="bi bi-chat-left-text me-1"></i>Leave feedback
                                                </button>
                                            @endif
                                            <div class="mt-2">
                                                <a href="{{ route('account.bookings.receipt', $appointment->id) }}" class="small">View receipt</a>
                                            </div>
                                        </div>

                                        {{-- FEEDBACK MODAL (one per Completed card) --}}
                                        <div class="modal fade" id="reviewModal-{{ $appointment->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <form method="POST" action="{{ route('account.bookings.review.store', $appointment->id) }}" class="modal-content nx-body" style="border-radius: var(--nx-radius-lg); overflow: hidden;">
                                                    @csrf
                                                    <div class="modal-header" style="border-color: var(--nx-border);">
                                                        <h2 class="h5 mb-0">@if($review) Edit feedback @else Leave feedback @endif</h2>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <p class="small nx-text-secondary">{{ $appointment->displayServiceName() }} — {{ $appointment->appointment_date->format('F j, Y') }} · ID {{ $appointment->referenceCode() }}</p>

                                                        <div class="mb-2">
                                                            <label class="form-label small">Feedback</label>
                                                            <textarea name="comment" rows="4" maxlength="1000" class="form-control" placeholder="Anything you'd like the team to know?" required>{{ old('comment', $review?->comment) }}</textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer" style="border-color: var(--nx-border);">
                                                        <button type="button" class="nx-btn nx-btn-outline" data-bs-dismiss="modal">Cancel</button>
                                                        <button class="nx-btn nx-btn-primary">Submit feedback</button>
                                                    </div>
                                                </form>
                                            </div>
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

                <div id="cr-cancel-fields" hidden>
                    <div class="mb-2">
                        <label class="form-label small" for="cr-category">Reason for cancellation <span class="text-danger">*</span></label>
                        <select name="reason_category" id="cr-category" class="form-select">
                            <option value="">Choose a reason</option>
                            @foreach(config('booking.cancellation_reasons', []) as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label small" id="cr-reason-label">Message to the team <span class="nx-text-secondary">(optional)</span></label>
                    <textarea name="reason" id="cr-reason" rows="3" maxlength="1000" class="form-control" placeholder="Let us know why, or anything else that helps."></textarea>
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

{{-- SHARED RESUBMIT PREVIEW — one full-size proof viewer for every inline
     resubmit form above (same borderless "View image" pattern as the
     top-up page, but shared so each card doesn't duplicate the modal). --}}
<style>
    .nx-preview-link {
        background: none; border: none; padding: 0;
        font-size: 13px; font-weight: 500;
        color: var(--nx-accent); text-decoration: none;
        cursor: pointer;
    }
    .nx-preview-link:hover {
        color: var(--nx-accent); text-decoration: underline;
        filter: brightness(1.15);
    }
</style>
<div class="modal fade" id="resubmit-preview-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 nx-body" style="border-radius: var(--nx-radius-lg); overflow: hidden;">
            <div class="modal-header" style="border-color: var(--nx-border);">
                <h2 class="h5 mb-0">Proof of payment</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-2 text-center">
                <img id="resubmit-preview-full" src="" alt="Proof of payment full size" class="img-fluid w-100">
            </div>
        </div>
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
        var cancelFields = document.getElementById('cr-cancel-fields');
        var categoryInput = document.getElementById('cr-category');
        var reasonInput = document.getElementById('cr-reason');
        var reasonLabel = document.getElementById('cr-reason-label');
        var dateInput = document.getElementById('cr-date');
        var timeInput = document.getElementById('cr-time');
        var submitBtn = document.getElementById('cr-submit');
        var base = "{{ url('account/bookings') }}";

        function syncCancelReason() {
            var isCancel = typeInput.value === 'cancellation';
            var needsDetails = isCancel && categoryInput.value === 'other';
            reasonInput.required = needsDetails;
            reasonInput.placeholder = isCancel ? 'Add details (optional)' : "Let us know why, or anything else that helps.";
            reasonLabel.innerHTML = isCancel
                ? (needsDetails
                    ? 'Details <span class="text-danger">*</span>'
                    : 'Details <span class="nx-text-secondary">(optional)</span>')
                : 'Message to the team <span class="nx-text-secondary">(optional)</span>';
        }

        function setMode(mode) {
            typeInput.value = mode;
            var isReschedule = mode === 'reschedule';
            rescheduleFields.hidden = !isReschedule;
            cancelFields.hidden = isReschedule;
            dateInput.required = isReschedule;
            timeInput.required = isReschedule;
            categoryInput.required = !isReschedule;
            if (isReschedule) categoryInput.value = '';
            title.textContent = isReschedule ? 'Request a reschedule' : 'Request a cancellation';
            submitBtn.textContent = isReschedule ? 'Send reschedule request' : 'Send cancellation request';
            modal.querySelectorAll('.js-cr-mode').forEach(function (b) {
                b.classList.toggle('active', b.dataset.mode === mode);
                b.classList.toggle('btn-secondary', b.dataset.mode === mode);
                b.classList.toggle('btn-outline-secondary', b.dataset.mode !== mode);
            });
            syncCancelReason();
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

        categoryInput.addEventListener('change', syncCancelReason);
    })();

    // Resubmit-proof file inputs (one inline form per invalid_proof card):
    // reveal the borderless "View image" preview button once an image is
    // chosen, showing it in the shared modal above. One delegated setup for
    // every card on the page.
    (function () {
        var previewModal = document.getElementById('resubmit-preview-modal');
        var previewFull = document.getElementById('resubmit-preview-full');
        var previewUrl = null;
        if (!previewModal) return;

        function setPreview(url) {
            if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
            if (url) {
                previewUrl = url;
                previewFull.src = url;
            } else {
                previewFull.removeAttribute('src');
            }
        }

        document.querySelectorAll('.resubmit-proof-input').forEach(function (input) {
            var btn = input.closest('form').querySelector('[data-preview-btn]');
            if (!btn) return;
            input.addEventListener('change', function () {
                setPreview(null);
                var file = input.files && input.files[0];
                if (!file || !file.type || file.type.indexOf('image/') !== 0) {
                    btn.classList.add('d-none');
                    return;
                }
                setPreview(URL.createObjectURL(file));
                btn.classList.remove('d-none');
            });
            btn.addEventListener('click', function () {
                new bootstrap.Modal(previewModal).show();
            });
        });
    })();
</script>
@endpush
@endsection
