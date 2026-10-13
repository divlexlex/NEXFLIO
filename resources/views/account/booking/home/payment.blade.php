{{-- Home Service Booking — Step 6: Proof of Payment + Submission
     (Phase 3B). POSTs to BookingController@homeStore, which validates
     everything server-side again via StoreWebHomeBookingRequest (never
     trusts these hidden fields on their own) — including re-validating the
     address fields — before writing anything. Same manual proof upload +
     Management verification as Branch Booking, no payment gateway. --}}
@extends('layouts.public')

@php($homeSteps = ['Service', 'Address', 'Date & Time', 'Details', 'Review', 'Payment', 'Submitted'])

@section('title', 'Book Home Service — Payment')

@section('content')
<header class="nx-hero">
    <div class="container py-5">
        <p class="nx-eyebrow mb-2">Home Service</p>
        <h1 class="mb-0">Proof of Payment</h1>
    </div>
</header>

<section class="nx-section" style="background: var(--nx-bg-page); padding-top: 40px;">
    <div class="container">
        @include('partials.booking.progress', ['step' => 6, 'steps' => $homeSteps])

        @if($errors->any())
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="alert alert-danger">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="nx-booking-panel">
                    <p class="nx-text-secondary mb-4" id="pay-lead-online">
                        Send your payment via GCash, Maya, GoTyme, bank transfer or card, then upload a screenshot or
                        photo of the receipt below. Our team verifies payments manually — you'll be
                        notified as soon as your payment is confirmed.
                    </p>
                    <p class="nx-text-secondary mb-4 d-none" id="pay-lead-cash">
                        No payment needed now — your booking is confirmed immediately and the amount is
                        payable at the branch.
                    </p>

                    <form method="POST" action="{{ route('account.booking.home.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="service_id" value="{{ $service->id }}">
                        <input type="hidden" name="appointment_date" value="{{ $date }}">
                        <input type="hidden" name="start_time" value="{{ $time }}">
                        <input type="hidden" name="personnel_id" value="{{ $personnel }}">
                        @if($notes)<input type="hidden" name="notes" value="{{ $notes }}">@endif
                        @include('partials.booking.carry-fields', ['address' => $address])

                        <label class="form-label" for="pay-method">Payment method</label>
                        <select name="method" id="pay-method" class="form-select mb-4" style="max-width: 260px;">
                            @foreach(config('payment.methods', []) as $value => $label)
                                <option value="{{ $value }}" @selected(old('method') === $value || (old('method') === null && $value === 'gcash'))>{{ $label }}</option>
                            @endforeach
                        </select>

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
                            amount is payable at the branch.
                        </div>

                        <div class="d-flex justify-content-between flex-wrap gap-2 mt-4">
                            <a href="{{ route('account.booking.home.review', array_merge(['service' => $service->id, 'date' => $date, 'time' => $time, 'personnel' => $personnel, 'notes' => $notes], $address)) }}" class="nx-btn nx-btn-outline">
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
        if (!method) return;

        function sync() {
            var isCash = method.value === 'cash';
            proofBlock.classList.toggle('d-none', isCash);
            proofInput.required = !isCash;
            if (isCash) proofInput.value = '';
            cashNote.classList.toggle('d-none', !isCash);
            leadOnline.classList.toggle('d-none', isCash);
            leadCash.classList.toggle('d-none', !isCash);
        }

        method.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush
