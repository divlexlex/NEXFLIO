{{-- Branch Booking — Step 6: Payment + Submission (Phase 3A).
     POSTs to BookingController@store, which validates everything
     server-side again via StoreWebBranchBookingRequest (never trusts these
     hidden fields on their own) before writing anything. No payment
     gateway — manual proof upload + Management verification for online
     methods, same as the existing mobile booking flow. Cash needs no proof:
     the booking is confirmed at once and the full amount is payable at the
     branch. When a reservation fee applies, only the fee is due online now
     and the balance settles at the branch. --}}
@extends('layouts.public')

@section('title', 'Book — Payment')

@section('content')
<section class="nx-booking-floating-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-7">
                @if($errors->any())
                    <div class="alert alert-danger">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="nx-booking-panel">
                    <div class="nx-booking-panel-header">
                        <div>
                            <p class="nx-eyebrow mb-1">Branch Booking</p>
                            <h1 class="h5 mb-0">Payment</h1>
                        </div>
                        @include('partials.booking.progress-compact', ['step' => 5])
                    </div>

                    <p class="nx-text-secondary mb-4" id="pay-lead-online">
                        @if($fee !== null)
                            Only the ₱{{ number_format($fee, 2) }} reservation fee is due now — send it via
                            GCash, Maya, GoTyme, bank transfer or card, then upload a screenshot or photo of
                            the receipt below. The ₱{{ number_format($balance, 2) }} balance is paid at the
                            branch. Our team verifies payments manually — you'll be notified as soon as your
                            booking is confirmed.
                        @else
                            Send the full amount via GCash, Maya, GoTyme, bank transfer or card, then upload a
                            screenshot or photo of the receipt below. Our team verifies payments manually —
                            you'll be notified as soon as your booking is confirmed.
                        @endif
                    </p>
                    <p class="nx-text-secondary mb-4 d-none" id="pay-lead-cash">
                        No payment needed now — your booking is confirmed immediately and you pay
                        <strong>₱{{ number_format($price, 2) }} in full at the branch</strong>.
                    </p>

                    <div class="nx-review-row">
                        <span class="nx-review-label">Service</span>
                        <span class="nx-review-value">{{ $service->name }}@if($variant) — {{ $variant->label }}@endif</span>
                    </div>
                    <div class="nx-review-row @if($fee === null) mb-4 @endif">
                        <span class="nx-review-label">Price</span>
                        <span class="nx-review-value">₱{{ number_format($price, 2) }}</span>
                    </div>
                    @if($fee !== null)
                        <div class="nx-review-row">
                            <span class="nx-review-label">Reservation fee (due now)</span>
                            <span class="nx-review-value">₱{{ number_format($fee, 2) }}</span>
                        </div>
                        <div class="nx-review-row mb-4">
                            <span class="nx-review-label">Balance (pay at the branch)</span>
                            <span class="nx-review-value">₱{{ number_format($balance, 2) }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('account.booking.branch.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="service_id" value="{{ $service->id }}">
                        @if($variant)<input type="hidden" name="variant_id" value="{{ $variant->id }}">@endif
                        <input type="hidden" name="appointment_date" value="{{ $date }}">
                        <input type="hidden" name="start_time" value="{{ $time }}">
                        <input type="hidden" name="personnel_id" value="{{ $personnel }}">
                        @if($notes)<input type="hidden" name="notes" value="{{ $notes }}">@endif
                        @if($guests)<input type="hidden" name="guests" value="{{ $guests }}">@endif

                        <label class="form-label" for="pay-method">Payment method</label>
                        <select name="method" id="pay-method" class="form-select mb-2" style="max-width: 260px;">
                            @foreach(config('payment.methods', []) as $value => $label)
                                <option value="{{ $value }}" @selected(old('method') === $value || (old('method') === null && $value === 'gcash'))>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="small nx-text-secondary mb-4 d-none" id="pay-receiving"></p>

                        <div id="pay-proof-block">
                            <label class="form-label">Proof of payment</label>
                            <div class="nx-upload-control mb-2">
                                <input type="file" name="payment_proof" id="pay-proof" accept="image/*" required class="form-control @error('payment_proof') is-invalid @enderror">
                                <p class="small nx-text-secondary mt-2 mb-0">JPG or PNG, up to 5MB.</p>
                            </div>
                            @error('payment_proof')
                                <div class="text-danger small mb-3">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="alert alert-info d-none" id="pay-cash-note">
                            <i class="bi bi-cash-coin me-1"></i>
                            Paying in cash — nothing to upload. Your booking is confirmed right away and the
                            amount is payable at the spa.
                        </div>

                        <div class="d-flex justify-content-between flex-wrap gap-2 mt-4">
                            <a href="{{ route('account.booking.branch.review', array_filter(['service' => $service->id, 'variant' => $variant?->id, 'date' => $date, 'time' => $time, 'personnel' => $personnel, 'notes' => $notes, 'guests' => $guests])) }}" class="nx-btn nx-btn-outline">
                                <i class="bi bi-arrow-left me-1"></i>Back
                            </a>
                            <button type="submit" class="nx-btn nx-btn-primary">
                                Submit Booking Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    (function () {
        var method = document.getElementById('pay-method');
        var proofBlock = document.getElementById('pay-proof-block');
        var proofInput = document.getElementById('pay-proof');
        var cashNote = document.getElementById('pay-cash-note');
        var leadOnline = document.getElementById('pay-lead-online');
        var leadCash = document.getElementById('pay-lead-cash');
        var receiving = document.getElementById('pay-receiving');
        var receivingMap = @json(collect(config('payment.receiving', []))->map(fn ($r) => $r['detail'] ?? ''));
        if (!method) return;

        function sync() {
            var isCash = method.value === 'cash';
            proofBlock.classList.toggle('d-none', isCash);
            proofInput.required = !isCash;
            if (isCash) proofInput.value = '';
            cashNote.classList.toggle('d-none', !isCash);
            leadOnline.classList.toggle('d-none', isCash);
            leadCash.classList.toggle('d-none', !isCash);
            var detail = receivingMap[method.value] || '';
            receiving.textContent = detail;
            receiving.classList.toggle('d-none', isCash || !detail);
        }

        method.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush
