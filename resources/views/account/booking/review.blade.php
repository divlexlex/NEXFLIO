{{-- Branch Booking — Step 5: Review (Phase 3A). Display only — nothing is
     written to the database until Submit on the Payment Proof step
     (BookingController@store). --}}
@extends('layouts.public')

@section('title', 'Book — Review')

@section('content')
<section class="nx-booking-floating-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-7">
                <div class="nx-booking-panel">
                    <div class="nx-booking-panel-header">
                        <div>
                            <p class="nx-eyebrow mb-1">Branch Booking</p>
                            <h1 class="h5 mb-0">Review Your Booking</h1>
                        </div>
                        @include('partials.booking.progress-compact', ['step' => 4])
                    </div>

                    <p class="nx-eyebrow mb-3">Booking Summary</p>

                    <div class="nx-review-row">
                        <span class="nx-review-label">Booking type</span>
                        <span class="nx-review-value">Branch</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Service</span>
                        <span class="nx-review-value">{{ $service->name }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Price</span>
                        <span class="nx-review-value">₱{{ number_format($service->price, 2) }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Duration</span>
                        <span class="nx-review-value">{{ $service->duration_minutes }} mins</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Date</span>
                        <span class="nx-review-value">{{ \Illuminate\Support\Carbon::parse($date)->format('F j, Y') }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Time</span>
                        <span class="nx-review-value">{{ \Illuminate\Support\Carbon::parse($time)->format('g:i A') }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Spa Personnel</span>
                        <span class="nx-review-value">{{ $personnelLabel }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Client</span>
                        <span class="nx-review-value">{{ $client->name }}</span>
                    </div>
                    @if($notes)
                        <div class="nx-review-row">
                            <span class="nx-review-label">Notes</span>
                            <span class="nx-review-value">{{ $notes }}</span>
                        </div>
                    @endif

                    <div class="alert alert-warning mt-4 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        A reservation fee proof of payment is required next. Your slot is only reserved
                        after our team verifies your payment — this booking is not confirmed yet.
                    </div>

                    <form method="GET" action="{{ route('account.booking.branch.payment') }}" class="d-flex justify-content-between flex-wrap gap-2 mt-4">
                        @include('partials.booking.carry-fields', ['service' => $service->id, 'date' => $date, 'time' => $time, 'personnel' => $personnel, 'notes' => $notes])
                        <a href="{{ route('account.booking.branch.details', ['service' => $service->id, 'date' => $date, 'time' => $time, 'personnel' => $personnel]) }}" class="nx-btn nx-btn-outline">
                            <i class="bi bi-arrow-left me-1"></i>Back
                        </a>
                        <button type="submit" class="nx-btn nx-btn-primary">
                            Continue to Payment <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
