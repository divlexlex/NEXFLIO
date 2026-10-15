{{-- Branch Booking (Phase 3A) and Home Service Booking (Phase 3B) — final
     Submitted step, shared by both flows (BookingController@success serves
     both account.booking.branch.success and account.booking.home.success).
      Online bookings land here with status Unverified
      (App\Enums\AppointmentStatus) — NOT confirmed; that only happens after
      Management verifies the payment (existing Admin verification workflow —
      App\Services\AppointmentService::transition). Cash bookings also land
      here Unverified now — a submitted request the branch confirms, with the
      full amount payable at the spa (see BookingController@store). Bookings
      that only paid the reservation fee online show the remaining branch
      balance. The address block below renders only when
     $appointment->address exists (Home Service only) — see
     App\Models\AppointmentAddress. --}}
@extends('layouts.public')

@section('title', 'Booking Request Submitted')

@section('content')
@php($isCash = $appointment->payment?->method === 'cash')
@php($isConfirmed = $appointment->status->value === 'booked')
@php($paidNow = (float) ($appointment->payment?->amount ?? 0))
@php($total = (float) ($appointment->price ?? 0))
@php($hasBalance = ! $isCash && $paidNow < $total)
<section class="nx-booking-floating-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-9 col-lg-6 text-center">
                <div class="nx-booking-success-icon">
                    <i class="bi {{ $isConfirmed ? 'bi-check-circle' : 'bi-hourglass-split' }}"></i>
                </div>
                <h1 class="mb-2" style="font-size: 30px;">
                    {{ $isConfirmed ? 'Booking Confirmed' : 'Booking Request Submitted' }}
                </h1>

                {{-- Client-facing Appointment ID — the code the client quotes
                     at the spa so the team can confirm the booking. Shown for
                     every booking type, alongside (never replacing) the
                     payment-state copy below. --}}
                <div class="nx-booking-panel text-center mb-4 py-3">
                    <p class="nx-eyebrow mb-1">Appointment ID</p>
                    <p class="h4 mb-1" style="letter-spacing: 1px;">{{ $appointment->referenceCode() }}</p>
                    <p class="small nx-text-secondary mb-0">Show this ID at the spa so we can confirm your booking.</p>
                </div>

                <div class="nx-booking-panel text-start mb-4">
                    <div class="nx-review-row">
                        <span class="nx-review-label">Service</span>
                        <span class="nx-review-value">{{ $appointment->displayServiceName() }}</span>
                    </div>
                    @if($appointment->price !== null)
                        <div class="nx-review-row">
                            <span class="nx-review-label">Price</span>
                            <span class="nx-review-value">₱{{ number_format((float) $appointment->price, 2) }}</span>
                        </div>
                    @endif
                    @if($hasBalance)
                        <div class="nx-review-row">
                            <span class="nx-review-label">Reservation fee (paid online)</span>
                            <span class="nx-review-value">₱{{ number_format($paidNow, 2) }}</span>
                        </div>
                        <div class="nx-review-row">
                            <span class="nx-review-label">Balance (pay at the branch)</span>
                            <span class="nx-review-value">₱{{ number_format($total - $paidNow, 2) }}</span>
                        </div>
                    @endif
                    @if($isCash)
                        <div class="nx-review-row">
                            <span class="nx-review-label">Payable at the branch</span>
                            <span class="nx-review-value">₱{{ number_format($total, 2) }} (Cash)</span>
                        </div>
                    @endif
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
                        <span class="nx-review-value">{{ $appointment->personnel->name ?? 'No preferred personnel' }}</span>
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
