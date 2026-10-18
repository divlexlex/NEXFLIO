@extends('layouts.admin')

@section('title', 'Payment Verification')

@section('content')
<h1 class="h3 mb-4">Payment Verification</h1>

<form method="GET" action="{{ route('admin.payments') }}" class="d-flex align-items-end flex-wrap gap-2 mb-3">
    <div>
        <label class="form-label small text-muted mb-0" for="status-filter">Status</label>
        <select name="status" id="status-filter" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            @foreach(['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected', 'all' => 'All', 'refund' => 'Refund', 'no_refund' => 'No refund'] as $value => $label)
                <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    {{-- Source filter — walk-ins (walk_in_name set) vs online bookings, kept
         alongside the other selects. Persisted like status/balance. --}}
    <div>
        <label class="form-label small text-muted mb-0" for="source-filter">Source</label>
        <select name="source" id="source-filter" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            @foreach(['all' => 'All sources', 'walkin' => 'Walk-ins', 'online' => 'Online'] as $value => $label)
                <option value="{{ $value }}" @selected($currentSource === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    {{-- Balance filter — narrows the active tab's cards by the appointment's
         derived balanceDue(), via query params so it survives reloads and tab
         switches (see PaymentController@index). --}}
    <div>
        <label class="form-label small text-muted mb-0" for="balance-filter">Amounts</label>
        <select name="balance" id="balance-filter" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            @foreach(['all' => 'All amounts', 'due' => 'With balance due', 'paid' => 'Paid in full'] as $value => $label)
                <option value="{{ $value }}" @selected($currentBalance === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="row g-3">
    @forelse($appointments as $appointment)
        @php($balanceDue = $appointment->balanceDue())
        @php($pendingPayment = $appointment->payments->first(fn ($p) => $p->status->value === 'pending'))
        {{-- Header badge from the payment state: Paid in full / Cancelled /
             Pending verification (has a pending online payment) / Booked
             (active, owing). A fresh walk-in has no payment row at all, so it
             reads Booked rather than Pending verification. --}}
        @php($badge = $balanceDue <= 0 ? ['Paid in full', 'success'] : ($appointment->status->value === 'cancelled' ? ['Cancelled', 'danger'] : ((($pendingPayment && $pendingPayment->method !== 'cash') || $appointment->status->value === 'unverified') ? ['Pending verification', 'warning'] : ['Booked', 'primary'])))
        <div class="col-md-6 col-xl-4">
            <div class="card p-3 h-100 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <strong>
                        {{ $appointment->clientName() ?? '—' }}
                        @if($appointment->walk_in_name !== null)
                            <span class="badge text-bg-secondary ms-1">Walk-in</span>
                        @endif
                    </strong>
                    <span>
                        @php($refundRemark = $appointment->cancellationRefundRemark())
                        @if($refundRemark === 'refund')
                            <span class="badge text-bg-primary me-1">Refund</span>
                        @elseif($refundRemark === 'no_refund')
                            <span class="badge text-bg-secondary me-1">No refund</span>
                        @endif
                        <span class="badge text-bg-{{ $badge[1] }}">{{ $badge[0] }}</span>
                    </span>
                </div>
                {{-- ONE list of what the customer availed under this single
                     Appointment ID: the main service, then each added service,
                     each with its price. Creation-time avails are part of the
                     appointment's services (no tag, no down-payment line); only
                     services added later from the Payments flow carry the
                     "Added" tag and their recorded 50% down payment. The
                     balance already sums them all via booked_price. --}}
                <div class="small mb-2" data-card-services>
                    <div class="d-flex justify-content-between gap-2">
                        <span>{{ $appointment->displayServiceName() }}</span>
                        <span class="text-muted text-nowrap">₱{{ number_format((float) ($appointment->service?->price ?? 0), 2) }}</span>
                    </div>
                    @foreach($appointment->addedServices as $added)
                        <div class="d-flex justify-content-between gap-2">
                            <span>
                                {{ $added->displayName() }}
                                @if($added->isPayment())
                                    <span class="badge text-bg-secondary ms-1">Added</span>
                                    {{-- Recorded 50% down payment for this added
                                         service (display only). --}}
                                    <span class="d-block text-muted">Down payment (50%): ₱{{ number_format(round((float) $added->price * 0.5, 2), 2) }}</span>
                                @endif
                            </span>
                            <span class="text-muted text-nowrap">₱{{ number_format($added->price, 2) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="small text-muted">
                    {{ $appointment->appointment_date?->format('M j') }}
                    {{ \Carbon\Carbon::parse($appointment->start_time)->format('g:i A') }}
                </div>
                <div class="small text-muted mb-2">
                    with {{ $appointment->personnel?->name ?? '—' }} · ID {{ $appointment->referenceCode() }}
                </div>

                <div class="small mb-2">
                    <div class="text-muted">Paid online ₱{{ number_format($appointment->paidOnline(), 2) }}</div>
                    <div class="text-muted">Paid at branch ₱{{ number_format($appointment->paidAtBranch(), 2) }}</div>
                    <div class="fw-bold">Balance due ₱{{ number_format($balanceDue, 2) }}</div>
                </div>

                {{-- Add another service. Booked or In-Service appointments
                     accept additions — see storeAdditionalService. Opens the
                     SAME shared modal; the balance reacts to the raised
                     booked_price on reload, and the modal's 50% down payment
                     applies to this added service. --}}
                @if(in_array($appointment->status->value, ['booked', 'in-service'], true))
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-2"
                            data-bs-toggle="modal" data-bs-target="#addedServiceModal"
                            data-appointment-id="{{ $appointment->id }}"
                            data-personnel-id="{{ $appointment->personnel_id }}">
                        <i class="bi bi-plus-lg me-1"></i>Add service
                    </button>
                @endif

                @foreach($appointment->payments as $payment)
                    <div class="border-top py-2 small">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <span>
                                @if($payment->kindLabel())
                                    <span class="badge text-bg-info me-1">{{ $payment->kindLabel() }}</span>
                                @endif
                                {{ strtoupper($payment->method) }} · ₱{{ number_format($payment->amount, 2) }}
                            </span>
                            @if($payment->status->value === 'verified')
                                <i class="bi bi-check-circle-fill" role="img" aria-label="Verified" title="Verified" style="color: var(--nex-success-text); font-size: 1.1rem;"></i>
                            @else
                                <span class="badge {{ match($payment->status->value) {
                                    'rejected' => 'text-bg-danger',
                                    default => 'text-bg-warning',
                                } }}">{{ ucfirst($payment->status->value) }}</span>
                            @endif
                        </div>
                        @if($payment->status->value === 'verified' && $payment->verifier)
                            <div class="text-muted">by {{ $payment->verifier->name }} · {{ $payment->verified_at?->format('M j, g:i A') }}</div>
                        @endif
                        @if($payment->proof_path)
                            <a href="{{ route('files.show', ['path' => $payment->proof_path]) }}" target="_blank" rel="noopener" class="small">View proof <i class="bi bi-box-arrow-up-right"></i></a>
                        @endif
                        @if($payment->status->value === 'rejected')
                            @if($payment->rejection_reason)
                                <div class="text-muted mt-1">Reason: {{ $payment->rejection_reason }}</div>
                            @endif
                            @if($payment->rejectionCategoryLabel())
                                <div class="text-muted mt-1">Category: {{ $payment->rejectionCategoryLabel() }}</div>
                            @endif
                        @endif
                        {{-- Refund state covers BOTH flows: the rejection flow
                             (amount_received set) and the cancellation-approval
                             flow (verified money held) — refundDueAmount()
                             prints the true amount for either. --}}
                        @if($payment->refund_status === 'requested')
                            <div class="mt-2">Refund requested: ₱{{ number_format($payment->refundDueAmount(), 2) }}</div>
                            <form method="POST" action="{{ route('admin.payments.refund-sent', $payment->id) }}" class="mt-2">
                                @csrf @method('PATCH')
                                <button class="btn btn-success btn-sm w-100">
                                    <i class="bi bi-send me-1"></i>Mark refund sent
                                </button>
                            </form>
                        @elseif($payment->refund_status === 'sent')
                            <div class="text-muted mt-1">Refund sent (₱{{ number_format($payment->refundDueAmount(), 2) }}){{ $payment->refund_sent_at ? ' · '.$payment->refund_sent_at->format('M j, g:i A') : '' }}</div>
                        @endif
                    </div>
                @endforeach

                @if($pendingPayment)
                    @if($pendingPayment->proof_path)
                        <a href="{{ route('files.show', ['path' => $pendingPayment->proof_path]) }}" target="_blank" rel="noopener">
                            <img src="{{ route('files.show', ['path' => $pendingPayment->proof_path]) }}"
                                 alt="Proof of payment" class="img-fluid rounded mb-2"
                                 style="max-height: 180px; object-fit: cover; width: 100%;">
                        </a>
                    @else
                        <div class="bg-light rounded text-center text-muted small py-4 mb-2">
                            No proof image (cash / walk-in)
                        </div>
                    @endif
                    {{-- Cash rows carry no online payment to verify — confirmation
                         happens from the Appointments side, so no Verify /
                         Reject buttons here (the row still shows above). --}}
                    @if($pendingPayment->method !== 'cash')
                    <div class="d-flex gap-2 mt-auto">
                        <form method="POST" action="{{ route('admin.payments.verify', $pendingPayment->id) }}" class="flex-fill">
                            @csrf @method('PATCH')
                            <button class="btn btn-success btn-sm w-100">
                                <i class="bi bi-check-lg me-1"></i>Verify
                            </button>
                        </form>
                        <button class="btn btn-outline-danger btn-sm flex-fill"
                                data-bs-toggle="modal" data-bs-target="#rejectModal"
                                data-id="{{ $pendingPayment->id }}"
                                data-amount="{{ $pendingPayment->amount }}">
                            <i class="bi bi-x-lg me-1"></i>Reject
                        </button>
                    </div>
                    @endif
                @endif

                {{-- Branch settlement: a Booked / In-Service appointment that
                     still owes money gets ONE settle form here, prefilled
                     with the balance due. Fully settled ones show Paid in
                     full instead. Posts to the existing branch-payment
                     route; its guards apply unchanged. Check-out is sequenced
                     after this: it renders only once the balance is 0. --}}
                @if(in_array($appointment->status->value, ['booked', 'in-service'], true))
                    <div class="mt-auto pt-3 border-top">
                        @if($balanceDue > 0)
                            <form method="POST" action="{{ route('admin.appointments.branch-payment', $appointment->id) }}" id="settle-form-{{ $appointment->id }}">
                                @csrf
                                <label class="form-label small mb-1" for="settle-amount-{{ $appointment->id }}">Record branch payment</label>
                                <div class="d-flex gap-2">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" name="amount" id="settle-amount-{{ $appointment->id }}" class="form-control"
                                               value="{{ number_format($balanceDue, 2, '.', '') }}" min="0.01" step="0.01"
                                               max="{{ number_format($balanceDue, 2, '.', '') }}" required>
                                    </div>
                                    <select name="method" class="form-select form-select-sm" style="max-width: 110px;">
                                        @foreach(config('payment.methods', []) as $value => $label)
                                            <option value="{{ $value }}" @selected($value === 'cash')>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" class="btn btn-sm btn-spa w-100 mt-2"
                                        data-bs-toggle="modal" data-bs-target="#settle-confirm-{{ $appointment->id }}">Record Payment</button>
                            </form>
                            {{-- RECORD CONFIRMATION MODAL: summarizes the live
                                 amount/method (populated on show) — only
                                 Confirm submits the form above unchanged. --}}
                            <div class="modal fade" id="settle-confirm-{{ $appointment->id }}" tabindex="-1"
                                 data-settle-modal data-form="settle-form-{{ $appointment->id }}"
                                 data-client="{{ $appointment->clientName() ?? '—' }}"
                                 data-service="{{ $appointment->displayServiceName() }}">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Confirm branch payment</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="mb-0" data-settle-summary></p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" form="settle-form-{{ $appointment->id }}" class="btn btn-spa">Confirm</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="small text-muted"><i class="bi bi-check-circle me-1"></i>Paid in full</div>
                        @endif
                    </div>
                @endif

                {{-- Check-out runs only after the balance is recorded: while
                     anything is owed there is NO check-out section (record
                     above first). Booked/In-Service at zero balance get the
                     Confirm check-out button (no ₱0 row is created — see
                     AppointmentController@checkout). Completed bookings keep
                     their own settle-then-badge block below; nothing renders
                     for Cancelled / No-Show / Unverified. --}}
                @if(in_array($appointment->status->value, ['booked', 'in-service'], true) && $balanceDue <= 0)
                    <div class="mt-auto pt-3 border-top">
                        <form method="POST" action="{{ route('admin.appointments.checkout', $appointment->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-success w-100">Confirm check-out</button>
                        </form>
                    </div>
                @endif

                {{-- Completed bookings: owing ones settle the remainder here
                     (status stays Completed); settled ones show the badge.
                     The Check out button above never appears for Completed. --}}
                @if($appointment->status->value === 'completed')
                    <div class="mt-auto pt-3 border-top">
                        @if($balanceDue > 0)
                            <p class="small text-muted mb-1">
                                Total ₱{{ number_format((float) ($appointment->booked_price ?? $appointment->service?->price ?? 0), 2) }}
                                · Paid online ₱{{ number_format($appointment->paidOnline(), 2) }}
                                · Paid at branch ₱{{ number_format($appointment->paidAtBranch(), 2) }}
                                · Balance due ₱{{ number_format($balanceDue, 2) }}
                            </p>
                            <form method="POST" action="{{ route('admin.appointments.checkout', $appointment->id) }}">
                                @csrf
                                <label class="form-label small mb-1" for="checkout-amount-{{ $appointment->id }}">Check out</label>
                                <div class="d-flex gap-2">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" name="amount" id="checkout-amount-{{ $appointment->id }}" class="form-control"
                                               value="{{ number_format($balanceDue, 2, '.', '') }}" min="0.01" step="0.01"
                                               max="{{ number_format($balanceDue, 2, '.', '') }}" required>
                                    </div>
                                    <select name="method" class="form-select form-select-sm" style="max-width: 110px;">
                                        @foreach(config('payment.methods', []) as $value => $label)
                                            <option value="{{ $value }}" @selected($value === 'cash')>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button class="btn btn-sm btn-success w-100 mt-2">Confirm check-out</button>
                            </form>
                        @else
                            <span class="badge text-bg-success">Settled · Paid in full</span>
                        @endif
                        <a href="{{ route('admin.appointments.receipt', $appointment->id) }}" class="btn btn-sm btn-outline-secondary w-100 mt-2">View receipt</a>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="card p-5 text-center text-muted">Nothing here — all caught up.</div>
        </div>
    @endforelse
</div>

<div class="mt-3">{{ $appointments->links() }}</div>

{{-- SHARED ADD-SERVICE MODAL (same partial the Appointments drawer uses) --}}
@include('admin.partials.added-service-modal')

{{-- REJECT MODAL --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="rejectForm" class="modal-content">
            @csrf @method('PATCH')
            <div class="modal-header">
                <h5 class="modal-title">Reject Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">The booking will be cancelled and the client notified with your reason.</p>
                <label class="form-label" for="reject-category">Reason</label>
                <select name="rejection_category" id="reject-category" class="form-select mb-3" required>
                    <option value="" selected disabled>Choose a reason…</option>
                    @foreach(config('payment.rejection_reasons', []) as $value => $reason)
                        <option value="{{ $value }}" @if($value === 'other')data-custom-reason="1"@endif>{{ $reason['label'] }}</option>
                    @endforeach
                </select>
                <div id="reject-custom-wrap" class="d-none mb-3">
                    <label class="form-label" for="reject-custom">Custom reason</label>
                    <textarea name="rejection_reason" id="reject-custom" class="form-control" rows="3"></textarea>
                </div>
                <div id="reject-amount-wrap" class="d-none mb-3">
                    <label class="form-label" for="reject-amount">Amount actually received (₱)</label>
                    <input type="number" name="amount_received" id="reject-amount" class="form-control"
                           min="0" step="0.01" placeholder="What the proof actually shows">
                    <p class="small text-muted mt-1 mb-0">What the manager saw on the proof — drives the client's refund option.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger">Reject Payment</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Record-confirmation summaries: each settle modal reads the LIVE
    // amount + method from its own branch-payment form on show, so an
    // edited figure is what the manager confirms.
    (function () {
        document.querySelectorAll('[data-settle-modal]').forEach(function (modal) {
            modal.addEventListener('show.bs.modal', function () {
                var form = document.getElementById(modal.dataset.form);
                var summary = modal.querySelector('[data-settle-summary]');
                if (!form || !summary) return;
                var amount = form.querySelector('input[name="amount"]');
                var method = form.querySelector('select[name="method"]');
                var amountText = '₱' + Number(amount ? amount.value : 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
                var methodText = method && method.options[method.selectedIndex]
                    ? method.options[method.selectedIndex].textContent.trim()
                    : '';
                summary.textContent = 'Record ' + amountText + ' via ' + methodText
                    + ' for ' + (modal.dataset.client || '—')
                    + ' — ' + (modal.dataset.service || '—') + '?';
            });
        });
    })();
</script>
<script>
    (function () {
        var modal = document.getElementById('rejectModal');
        var form = document.getElementById('rejectForm');
        var category = document.getElementById('reject-category');
        var customWrap = document.getElementById('reject-custom-wrap');
        var customInput = document.getElementById('reject-custom');
        var amountWrap = document.getElementById('reject-amount-wrap');
        var amountInput = document.getElementById('reject-amount');
        // Eligibility flags straight from config/payment.php (see
        // PaymentController@index 'rejectionFlags') — the script only reads
        // flags, it never names a category key itself.
        var flags = @json($rejectionFlags);

        function syncReject() {
            var selected = category.options[category.selectedIndex];
            var needsCustom = selected && selected.dataset.customReason === '1';
            var f = flags[category.value] || {};
            var needsAmount = !!f.topup_eligible;

            customWrap.classList.toggle('d-none', !needsCustom);
            customInput.required = !!needsCustom;
            if (!needsCustom) customInput.value = '';

            amountWrap.classList.toggle('d-none', !needsAmount);
            amountInput.required = needsAmount;
            if (!needsAmount) amountInput.value = '';
        }

        category.addEventListener('change', syncReject);
        modal.addEventListener('show.bs.modal', (event) => {
            form.action = "{{ url('admin/payments') }}/" + event.relatedTarget.dataset.id + "/reject";
            form.reset();
            amountInput.max = event.relatedTarget.dataset.amount || '';
            syncReject();
        });
    })();
</script>
@endpush
