@extends('layouts.admin')

@section('title', 'Leave Requests')

@section('content')
<h1 class="h3 mb-4">Leave Requests</h1>

<ul class="nav nav-pills mb-3">
    @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'all' => 'All'] as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ $currentStatus === $key ? 'active' : '' }}"
               style="{{ $currentStatus === $key ? 'background: var(--spa-espresso);' : 'color: var(--spa-espresso);' }}"
               href="{{ route('admin.leaves', ['status' => $key]) }}">{{ $label }}</a>
        </li>
    @endforeach
</ul>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Dates</th>
                    <th>Type</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($leaves as $leave)
                    <tr>
                        <td>{{ $leave->user->name ?? '—' }}</td>
                        <td>
                            {{ $leave->start_date->format('M j') }}
                            @if(! $leave->start_date->isSameDay($leave->end_date))
                                – {{ $leave->end_date->format('M j, Y') }}
                            @else
                                , {{ $leave->start_date->format('Y') }}
                            @endif
                        </td>
                        <td><span class="badge text-bg-light border">{{ ucfirst($leave->type) }}</span></td>
                        <td class="text-muted small" style="max-width: 260px;">{{ $leave->reason }}</td>
                        <td>
                            <span class="badge {{ match($leave->status) {
                                'approved' => 'text-bg-success',
                                'denied' => 'text-bg-danger',
                                default => 'text-bg-warning',
                            } }}">{{ ucfirst($leave->status) }}</span>
                            @if($leave->reviewer)
                                <div class="small text-muted">by {{ $leave->reviewer->name }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($leave->status === 'pending')
                                <button class="btn btn-sm btn-success"
                                        data-bs-toggle="modal" data-bs-target="#reviewModal"
                                        data-id="{{ $leave->id }}" data-decision="approved">Approve</button>
                                <button class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal" data-bs-target="#reviewModal"
                                        data-id="{{ $leave->id }}" data-decision="denied">Deny</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No leave requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $leaves->links() }}
</div>

{{-- REVIEW MODAL --}}
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="reviewForm" class="modal-content">
            @csrf @method('PATCH')
            <input type="hidden" name="status" id="review-status">
            <div class="modal-header">
                <h5 class="modal-title" id="review-title">Review Leave</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Notes for the staff member (optional)</label>
                <textarea name="review_notes" class="form-control" rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-spa" id="review-submit">Confirm</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('reviewModal').addEventListener('show.bs.modal', (event) => {
        const btn = event.relatedTarget;
        document.getElementById('reviewForm').action = "{{ url('admin/leaves') }}/" + btn.dataset.id + "/review";
        document.getElementById('review-status').value = btn.dataset.decision;
        document.getElementById('review-title').textContent =
            btn.dataset.decision === 'approved' ? 'Approve Leave' : 'Deny Leave';
    });
</script>
@endpush
