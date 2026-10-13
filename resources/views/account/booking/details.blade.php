{{-- Branch Booking — Step 4: Appointment Details (Phase 3A). Branch-only:
     no home address / landmark / home-service notes are collected here —
     those belong to Phase 3B (Home Service), not yet built. Client
     identity is read from the authenticated account, not re-entered. --}}
@extends('layouts.public')

@section('title', 'Book — Appointment Details')

@section('content')
<section class="nx-booking-floating-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-7">
                <div class="nx-booking-panel">
                    <div class="nx-booking-panel-header">
                        <div>
                            <p class="nx-eyebrow mb-1">Branch Booking</p>
                            <h1 class="h5 mb-0">Appointment Details</h1>
                        </div>
                        @include('partials.booking.progress-compact', ['step' => 3])
                    </div>

                    <p class="nx-eyebrow mb-3">Your Information</p>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Name</span>
                        <span class="nx-review-value">{{ $client->name }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Email</span>
                        <span class="nx-review-value">{{ $client->email }}</span>
                    </div>

                    <hr class="my-4" style="border-color: var(--nx-border);">

                    <form method="GET" action="{{ route('account.booking.branch.review') }}">
                        @include('partials.booking.carry-fields', ['service' => $service->id, 'variant' => $variant?->id, 'date' => $date, 'time' => $time, 'personnel' => $personnel])

                        @if($needsGuests)
                            {{-- Reservation-fee services without package tiers
                                 price the fee off the guest count (1→Single,
                                 2→Couple, 3+→Barkada — see
                                 config/booking.php), so the count is required
                                 here and validated again server-side in
                                 StoreWebBranchBookingRequest. --}}
                            <label for="guests" class="form-label">Number of guests <span class="text-danger">*</span></label>
                            <input type="number" name="guests" id="guests" value="{{ old('guests', $guests ?? 1) }}"
                                   min="1" max="30" step="1" required class="form-control" style="max-width: 160px;">
                            <p class="small nx-text-secondary mt-2 mb-3">
                                @php($tiers = config('booking.reservation_fee.tiers', []))
                                Your reservation fee is based on this:
                                1 guest ₱{{ number_format($tiers['Single'] ?? 0) }} ·
                                2 guests ₱{{ number_format($tiers['Couple'] ?? 0) }} ·
                                3 or more ₱{{ number_format($tiers['Barkada'] ?? 0) }}.
                                It's a down payment — deducted from your total, balance paid at the branch.
                            </p>
                        @endif

                        <label for="notes" class="form-label">Notes for the branch <span class="nx-text-secondary" style="font-weight:400;">(optional)</span></label>
                        <textarea name="notes" id="notes" rows="4" maxlength="1000" class="form-control" placeholder="Anything the team should know before your visit?">{{ $notes }}</textarea>

                        <div class="d-flex justify-content-between flex-wrap gap-2 mt-4">
                            <a href="{{ route('account.booking.branch.schedule', array_filter(['service' => $service->id, 'variant' => $variant?->id, 'date' => $date])) }}" class="nx-btn nx-btn-outline">
                                <i class="bi bi-arrow-left me-1"></i>Back
                            </a>
                            <button type="submit" class="nx-btn nx-btn-primary">
                                Continue <i class="bi bi-arrow-right ms-1"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
