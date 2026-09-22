{{-- Home Service Booking — Step 5: Review (Phase 3B). Display only —
     nothing is written to the database until Submit on the Payment Proof
     step (BookingController@homeStore). --}}
@extends('layouts.public')

@php($homeSteps = ['Service', 'Address', 'Date & Time', 'Details', 'Review', 'Payment', 'Submitted'])

@section('title', 'Book Home Service — Review')

@section('content')
<header class="nx-hero">
    <div class="container py-5">
        <p class="nx-eyebrow mb-2">Home Service</p>
        <h1 class="mb-0">Review Your Booking</h1>
    </div>
</header>

<section class="nx-section" style="background: var(--nx-bg-page); padding-top: 40px;">
    <div class="container">
        @include('partials.booking.progress', ['step' => 5, 'steps' => $homeSteps])

        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="nx-booking-panel">
                    <p class="nx-eyebrow mb-3">Booking Summary</p>

                    <div class="nx-review-row">
                        <span class="nx-review-label">Booking type</span>
                        <span class="nx-review-value">Home Service</span>
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
                    <div class="nx-review-row">
                        <span class="nx-review-label">Service address</span>
                        <span class="nx-review-value">{{ collect([$address['street_address'], $address['barangay'], $address['city_municipality'], $address['province']])->filter()->implode(', ') }}{{ $address['postal_code'] ? ' ' . $address['postal_code'] : '' }}</span>
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

                    <form method="GET" action="{{ route('account.booking.home.payment') }}" class="d-flex justify-content-between flex-wrap gap-2 mt-4">
                        @include('partials.booking.carry-fields', ['service' => $service->id, 'date' => $date, 'time' => $time, 'personnel' => $personnel, 'notes' => $notes, 'address' => $address])
                        <a href="{{ route('account.booking.home.details', array_merge(['service' => $service->id, 'date' => $date, 'time' => $time, 'personnel' => $personnel], $address)) }}" class="nx-btn nx-btn-outline">
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
