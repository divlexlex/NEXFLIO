@extends('layouts.admin')

@section('title', 'Change Requests')

@section('content')
<h1 class="h3 mb-1">Appointment Change Requests</h1>
<p class="text-muted small mb-4">
    Clients can request a cancellation or a reschedule from the Website. Nothing on the
    appointment moves until you approve it here — approving a cancellation cancels the
    booking (and rejects any unverified payment); approving a reschedule moves the
    appointment to the requested date, time and staff after re-checking that slot is free.
</p>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<ul class="nav nav-pills mb-3">
    @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ $currentStatus === $key ? 'active' : '' }}"
               style="{{ $currentStatus === $key ? 'background: var(--spa-espresso);' : 'color: var(--spa-espresso);' }}"
               href="{{ route('admin.appointment-requests', ['status' => $key]) }}">
                {{ $label }}@if($key === 'pending' && $pendingCount) <span class="badge text-bg-light ms-1">{{ $pendingCount }}</span>@endif
            </a>
        </li>
    @endforeach
</ul>

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
                        <td class="text-muted small" style="max-width: 240px;">{{ $req->reason ?: '—' }}</td>
                        <td>
                            <span class="badge {{ match($req->status) {
                                'approved' => 'text-bg-success',
                                'rejected' => 'text-bg-danger',
                                default => 'text-bg-warning',
                            } }}">{{ ucfirst($req->status) }}</span>
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
                                        data-id="{{ $req->id }}" data-decision="approve"
                                        data-summary="{{ $req->type === 'reschedule' ? 'Move this appointment to the requested slot?' : 'Cancel this appointment?' }}">
                                    Approve
                                </button>
                                <button class="btn btn-sm btn-outline-danger js-review"
                                        data-bs-toggle="modal" data-bs-target="#reviewRequestModal"
                                        data-id="{{ $req->id }}" data-decision="reject"
                                        data-summary="Decline this request? The appointment stays as it is.">
                                    Reject
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No change requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $requests->links() }}
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
                <label class="form-label">Note for the client (optional)</label>
                <textarea name="review_note" class="form-control" rows="3"></textarea>
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
        document.getElementById('reviewRequestForm').action =
            "{{ url('admin/appointment-requests') }}/" + btn.dataset.id + "/review";
        document.getElementById('rr-decision').value = decision;
        document.getElementById('rr-title').textContent = decision === 'approve' ? 'Approve Request' : 'Reject Request';
        document.getElementById('rr-submit').textContent = decision === 'approve' ? 'Approve' : 'Reject';
        document.getElementById('rr-summary').textContent = btn.dataset.summary || '';
    });
</script>
@endpush
