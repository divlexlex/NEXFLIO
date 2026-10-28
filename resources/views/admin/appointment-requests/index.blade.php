@extends('layouts.admin')

@section('title', 'Change Requests')

@section('content')
<h1 class="h3 mb-1">Appointment Change Requests</h1>

<form method="GET" action="{{ route('admin.appointment-requests') }}" id="requests-filter-form" class="row g-2 align-items-end mb-3">
    <div class="col-md-3">
        <label class="form-label small mb-0" for="filter-status">Status</label>
        <select id="filter-status" name="status" class="form-select">
            @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
                <option value="{{ $key }}" {{ $currentStatus === $key ? 'selected' : '' }}>{{ $label }}@if($key === 'pending' && $pendingCount) ({{ $pendingCount }})@endif</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label small mb-0" for="filter-type">Type</label>
        <select id="filter-type" name="type" class="form-select">
            <option value="all" {{ $currentType === 'all' ? 'selected' : '' }}>All requests</option>
            <option value="cancellation" {{ $currentType === 'cancellation' ? 'selected' : '' }}>Cancellation</option>
            <option value="reschedule" {{ $currentType === 'reschedule' ? 'selected' : '' }}>Reschedule</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label small mb-0" for="filter-q">Search</label>
        <input type="search" id="filter-q" name="q" value="{{ $currentQ }}" class="form-control" placeholder="Search client or service…" autocomplete="off">
    </div>
</form>
{{-- Refreshable region: the count line + table + pagination. Swapped in
     place by nx-refresh.js (no page reload). The filter form above stays
     outside on purpose, so text being typed in the search box never resets
     on refresh (the pending count inside its dropdown therefore does not
     update — the table itself does). --}}
<div id="nx-refresh-region">
@if($requests->total() > 0 || $totalRequests > 0)
    <p class="text-muted small mb-2">Showing {{ $requests->total() }} of {{ $totalRequests }} requests</p>
@endif

<div class="card p-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Appointment</th>
                    <th>Request</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $req)
                    @php($appt = $req->appointment)
                    <tr>
                        <td>{{ $req->requester->name ?? '—' }}</td>
                        <td class="small">
                            @if($appt)
                                <div>{{ $appt->service->name ?? 'Service' }}</div>
                                <div class="text-muted">
                                    {{ $appt->appointment_date->format('M j, Y') }} ·
                                    {{ \Illuminate\Support\Carbon::parse($appt->start_time)->format('g:i A') }}
                                    @if($appt->personnel) · {{ $appt->personnel->name }} @endif
                                </div>
                            @else
                                <span class="text-muted">appointment deleted</span>
                            @endif
                        </td>
                        <td class="small">
                            @if($req->type === \App\Models\AppointmentChangeRequest::TYPE_RESCHEDULE)
                                <span class="badge text-bg-info">Reschedule</span>
                                <div class="mt-1">
                                    → {{ \Illuminate\Support\Carbon::parse($req->requested_date)->format('M j, Y') }}
                                    at {{ \Illuminate\Support\Carbon::parse($req->requested_start_time)->format('g:i A') }}
                                </div>
                                <div class="text-muted">
                                    {{ $req->requestedPersonnel->name ?? 'No staff preference' }}
                                </div>
                            @else
                                <span class="badge text-bg-secondary">Cancellation</span>
                            @endif
                        </td>
                        <td class="text-muted small" style="max-width: 240px;">
                            @if($req->type === \App\Models\AppointmentChangeRequest::TYPE_CANCELLATION && $req->cancellationReasonLabel())
                                <div class="fw-semibold text-body">{{ $req->cancellationReasonLabel() }}</div>
                                @if($req->reason)
                                    <div>{{ $req->reason }}</div>
                                @endif
                            @else
                                {{ $req->reason ?: '—' }}
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ match($req->status) {
                                'approved' => 'text-bg-success',
                                'rejected' => 'text-bg-danger',
                                default => 'text-bg-warning',
                            } }}">{{ ucfirst($req->status) }}</span>
                            @if($req->type === \App\Models\AppointmentChangeRequest::TYPE_CANCELLATION && $req->status === \App\Models\AppointmentChangeRequest::STATUS_APPROVED)
                                @if($req->refund_decision === 'refundable')
                                    <div class="mt-1"><span class="badge text-bg-success">Refundable ₱{{ number_format($appt?->verifiedPaid() ?? 0, 2) }}</span></div>
                                @elseif($req->refund_decision === 'non_refundable')
                                    <div class="mt-1"><span class="badge text-bg-secondary">Non-refundable</span></div>
                                @endif
                            @endif
                            @if($req->reviewer)
                                <div class="small text-muted">by {{ $req->reviewer->name }}</div>
                            @endif
                            @if($req->review_note)
                                <div class="small text-muted fst-italic">“{{ $req->review_note }}”</div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($req->isPending() && $appt)
                                <button class="btn btn-sm btn-success js-review"
                                        data-bs-toggle="modal" data-bs-target="#reviewRequestModal"
                                        data-id="{{ $req->id }}" data-decision="approve" data-type="{{ $req->type }}"
                                        data-summary="{{ $req->type === 'reschedule' ? 'Move this appointment to the requested slot?' : 'Cancel this appointment?' }}">
                                    Approve
                                </button>
                                <button class="btn btn-sm btn-outline-danger js-review"
                                        data-bs-toggle="modal" data-bs-target="#reviewRequestModal"
                                        data-id="{{ $req->id }}" data-decision="reject" data-type="{{ $req->type }}"
                                        data-summary="Decline this request? The appointment stays as it is.">
                                    Reject
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No requests match your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $requests->links() }}
</div>
</div>

{{-- REVIEW MODAL --}}
<div class="modal fade" id="reviewRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="reviewRequestForm" class="modal-content">
            @csrf @method('PATCH')
            <input type="hidden" name="decision" id="rr-decision">
            <div class="modal-header">
                <h5 class="modal-title" id="rr-title">Review Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small" id="rr-summary"></p>
                <div id="rr-note-wrap">
                    <label class="form-label">Note for the client (optional)</label>
                    <textarea name="review_note" id="rr-note" class="form-control" rows="3"></textarea>
                </div>
                <div id="rr-refund-wrap" class="d-none">
                    <label class="form-label" for="rr-refund">Refund decision <span class="text-danger">*</span></label>
                    <select name="refund_decision" id="rr-refund" class="form-select">
                        <option value="">Choose…</option>
                        <option value="refundable">Refundable</option>
                        <option value="non_refundable">Non-refundable</option>
                    </select>
                    <p class="small text-muted mt-1 mb-0">Applies to what the client already paid online.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa" id="rr-submit">Confirm</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('reviewRequestModal').addEventListener('show.bs.modal', (event) => {
        const btn = event.relatedTarget;
        const decision = btn.dataset.decision;
        const isCancelApprove = decision === 'approve' && btn.dataset.type === 'cancellation';
        document.getElementById('reviewRequestForm').action =
            "{{ url('admin/appointment-requests') }}/" + btn.dataset.id + "/review";
        document.getElementById('rr-decision').value = decision;
        document.getElementById('rr-title').textContent = decision === 'approve' ? 'Approve Request' : 'Reject Request';
        document.getElementById('rr-submit').textContent = decision === 'approve' ? 'Approve' : 'Reject';
        document.getElementById('rr-summary').textContent = btn.dataset.summary || '';
        // Cancellation approvals decide refundable / non-refundable
        // instead of a note; every other review keeps the optional note.
        document.getElementById('rr-note-wrap').classList.toggle('d-none', isCancelApprove);
        document.getElementById('rr-refund-wrap').classList.toggle('d-none', !isCancelApprove);
        document.getElementById('rr-refund').required = isCancelApprove;
        if (!isCancelApprove) {
            document.getElementById('rr-refund').value = '';
        } else {
            document.getElementById('rr-note').value = '';
        }
    });
</script>
<script>
    // Automatic filter bar (same pattern as the Admin Services page):
    // selects submit immediately, the search input submits ~500ms after
    // the last keystroke. No buttons — resetting to All/empty re-submits
    // and restores the full list.
    (function () {
        const form = document.getElementById('requests-filter-form');
        if (!form) return;
        const search = document.getElementById('filter-q');
        let timer = null;

        form.querySelectorAll('#filter-status, #filter-type').forEach((sel) => {
            sel.addEventListener('change', () => form.submit());
        });

        search.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => form.submit(), 500);
        });

        // Keep the typing flow smooth across reloads: focus back into the
        // search box with the caret at the end of the current text.
        if (search.value) {
            search.focus();
            try { search.setSelectionRange(search.value.length, search.value.length); } catch (e) { /* older browsers */ }
        }
    })();
</script>
@endpush
