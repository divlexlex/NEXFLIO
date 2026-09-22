{{-- Branch Booking (Phase 3A) and Home Service Booking (Phase 3B) — final
     Submitted step, shared by both flows (BookingController@success serves
     both account.booking.branch.success and account.booking.home.success).
     The appointment now exists with status Unverified
     (App\Enums\AppointmentStatus) — it is NOT confirmed. Wording here must
     never claim confirmation; that only happens after Management verifies
     the payment (existing Admin verification workflow —
     App\Services\AppointmentService::transition). The address block below
     renders only when $appointment->address exists (Home Service only) —
     see App\Models\AppointmentAddress. --}}
@extends('layouts.public')

@section('title', 'Booking Request Submitted')

@section('content')
<section class="nx-booking-floating-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-9 col-lg-6 text-center">
                <div class="nx-booking-success-icon">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <h1 class="mb-2" style="font-size: 30px;">Booking Request Submitted</h1>
                <p class="nx-text-secondary mb-4">
                    Your booking is <strong>pending verification</strong>. Our team is reviewing your
                    proof of payment and will confirm your appointment shortly — you'll get a
                    notification the moment it's verified.
                </p>

                <div class="nx-booking-panel text-start mb-4">
                    <div class="nx-review-row">
                        <span class="nx-review-label">Service</span>
                        <span class="nx-review-value">{{ $appointment->service->name ?? 'Service' }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Date</span>
                        <span class="nx-review-value">{{ $appointment->appointment_date->format('F j, Y') }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Time</span>
                        <span class="nx-review-value">{{ \Illuminate\Support\Carbon::parse($appointment->start_time)->format('g:i A') }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Spa Personnel</span>
                        <span class="nx-review-value">{{ $appointment->personnel->name ?? '—' }}</span>
                    </div>
                    @if($appointment->address)
                        <div class="nx-review-row">
                            <span class="nx-review-label">Booking type</span>
                            <span class="nx-review-value">Home Service</span>
                        </div>
                        <div class="nx-review-row">
                            <span class="nx-review-label">Service address</span>
                            <span class="nx-review-value">{{ $appointment->address->formatted() }}</span>
                        </div>
                    @endif
                    <div class="nx-review-row">
                        <span class="nx-review-label">Status</span>
                        <span class="nx-status-badge nx-status-pending">{{ $appointment->status->label() }}</span>
                    </div>
                </div>

                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <a href="{{ route('account.bookings') }}" class="nx-btn nx-btn-primary">View My Bookings</a>
                    <a href="{{ route('landing') }}" class="nx-btn nx-btn-outline">Back to Home</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
