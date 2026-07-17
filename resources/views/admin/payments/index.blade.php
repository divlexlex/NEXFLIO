@extends('layouts.admin')

@section('title', 'Payment Verification')

@section('content')
<h1 class="h3 mb-4">Payment Verification</h1>

<ul class="nav nav-pills mb-3">
    @foreach(['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ $currentStatus === $key ? 'active' : '' }}"
               style="{{ $currentStatus === $key ? 'background: var(--spa-espresso);' : 'color: var(--spa-espresso);' }}"
               href="{{ route('admin.payments', ['status' => $key]) }}">{{ $label }}</a>
        </li>
    @endforeach
</ul>

<div class="row g-3">
    @forelse($payments as $payment)
        <div class="col-md-6 col-xl-4">
            <div class="card p-3 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <strong>{{ $payment->appointment?->clientName() ?? '—' }}</strong>
                        <div class="small text-muted">
                            {{ $payment->appointment?->service?->name }} ·
                            {{ $payment->appointment?->appointment_date?->format('M j') }}
                            {{ $payment->appointment?->start_time }}
                        </div>
                        <div class="small text-muted">
                            with {{ $payment->appointment?->personnel?->name ?? '—' }} · {{ strtoupper($payment->method) }}
                        </div>
                    </div>
                    <span class="fs-5 fw-bold text-gold">₱{{ number_format($payment->amount, 2) }}</span>
                </div>

                @if($payment->proof_path)
                    <a href="{{ asset('storage/' . $payment->proof_path) }}" target="_blank" rel="noopener">
                        <img src="{{ asset('storage/' . $payment->proof_path) }}"
                             alt="Proof of payment" class="img-fluid rounded mb-2"
                             style="max-height: 180px; object-fit: cover; width: 100%;">
                    </a>
                @else
                    <div class="bg-light rounded text-center text-muted small py-4 mb-2">
                        No proof image (cash / walk-in)
                    </div>
                @endif

                @if($payment->status->value === 'pending')
                    <div class="d-flex gap-2 mt-auto">
                        <form method="POST" action="{{ route('admin.payments.verify', $payment->id) }}" class="flex-fill">
                            @csrf @method('PATCH')
                            <button class="btn btn-success btn-sm w-100">
                                <i class="bi bi-check-lg me-1"></i>Verify
                            </button>
                        </form>
                        <button class="btn btn-outline-danger btn-sm flex-fill"
                                data-bs-toggle="modal" data-bs-target="#rejectModal"
                                data-id="{{ $payment->id }}">
                            <i class="bi bi-x-lg me-1"></i>Reject
                        </button>
                    </div>
                @else
                    <div class="small mt-auto">
                        <span class="badge {{ $payment->status->value === 'verified' ? 'text-bg-success' : 'text-bg-danger' }}">
                            {{ ucfirst($payment->status->value) }}
                        </span>
                        @if($payment->verifier)
                            by {{ $payment->verifier->name }} · {{ $payment->verified_at?->format('M j, g:i A') }}
                        @endif
                        @if($payment->rejection_reason)
                            <div class="text-muted mt-1">Reason: {{ $payment->rejection_reason }}</div>
                        @endif
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

<div class="mt-3">{{ $payments->links() }}</div>

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
                <label class="form-label">Reason</label>
                <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
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
    document.getElementById('rejectModal').addEventListener('show.bs.modal', (event) => {
        document.getElementById('rejectForm').action =
            "{{ url('admin/payments') }}/" + event.relatedTarget.dataset.id + "/reject";
    });
</script>
@endpush
