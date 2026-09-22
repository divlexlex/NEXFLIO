{{-- Booking type entry — the no-JS / direct-URL fallback for the
     "Book an Appointment" modal (partials/booking/service-picker-modal).
     Branch Booking (Phase 3A) is the real, working flow; Home Service
     Booking (Phase 3B) is intentionally stubbed to "Coming Soon" here to
     match the modal — its backend/routes are untouched and fully working
     (see BookingController's home.* methods), just not linked from either
     entry point for now. --}}
@extends('layouts.public')

@section('title', 'Book an Appointment')

@section('content')
<section class="nx-booking-floating-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-9">
                <div class="text-center mb-4">
                    <p class="nx-eyebrow mb-1">Book an Appointment</p>
                    <h1 class="h4 mb-0">How would you like to be served?</h1>
                </div>
                <div class="row g-4 justify-content-center">
                    <div class="col-12 col-md-5">
                        <a href="{{ route('account.booking.branch.service') }}" class="nx-select-card h-100 d-flex flex-column" style="padding: 28px;">
                            <div class="nx-value-icon mb-3"><i class="bi bi-shop"></i></div>
                            <h2 class="h5 mb-2">Branch Booking</h2>
                            <p class="nx-text-secondary mb-3">Visit our Maypajo studio for your treatment. Choose a service, pick a date and time, and reserve your slot.</p>
                            <span class="nx-btn nx-btn-primary mt-auto align-self-start">Continue <i class="bi bi-arrow-right ms-1"></i></span>
                        </a>
                    </div>
                    <div class="col-12 col-md-5">
                        {{-- Not a link — see class comment above: intentionally
                             disabled for now, matching the modal's stub. --}}
                        <div class="nx-select-card h-100 d-flex flex-column" style="padding: 28px; opacity: .65; cursor: default;">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="nx-value-icon mb-0"><i class="bi bi-house-heart"></i></div>
                                <span class="nx-badge">Coming Soon</span>
                            </div>
                            <h2 class="h5 mb-2">Home Service</h2>
                            <p class="nx-text-secondary mb-0">Have a specialist come to you. This will be available through the Website soon — for now, book Home Service through the Perfect Nails app.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
