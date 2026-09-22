{{-- Home Service Booking — Step 4: Appointment Details (Phase 3B). Client
     identity is read from the authenticated account, not re-entered — the
     service address was already collected in Step 2. --}}
@extends('layouts.public')

@php($homeSteps = ['Service', 'Address', 'Date & Time', 'Details', 'Review', 'Payment', 'Submitted'])

@section('title', 'Book Home Service — Appointment Details')

@section('content')
<header class="nx-hero">
    <div class="container py-5">
        <p class="nx-eyebrow mb-2">Home Service</p>
        <h1 class="mb-0">Appointment Details</h1>
    </div>
</header>

<section class="nx-section" style="background: var(--nx-bg-page); padding-top: 40px;">
    <div class="container">
        @include('partials.booking.progress', ['step' => 4, 'steps' => $homeSteps])

        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="nx-booking-panel">
                    <p class="nx-eyebrow mb-3">Your Information</p>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Name</span>
                        <span class="nx-review-value">{{ $client->name }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Email</span>
                        <span class="nx-review-value">{{ $client->email }}</span>
                    </div>
                    <div class="nx-review-row">
                        <span class="nx-review-label">Service address</span>
                        <span class="nx-review-value">{{ collect([$address['street_address'], $address['barangay'], $address['city_municipality'], $address['province']])->filter()->implode(', ') }}{{ $address['postal_code'] ? ' ' . $address['postal_code'] : '' }}</span>
                    </div>

                    <hr class="my-4" style="border-color: var(--nx-border);">

                    <form method="GET" action="{{ route('account.booking.home.review') }}">
                        @include('partials.booking.carry-fields', ['service' => $service->id, 'date' => $date, 'time' => $time, 'personnel' => $personnel, 'address' => $address])

                        <label for="notes" class="form-label">Notes for the specialist <span class="nx-text-secondary" style="font-weight:400;">(optional)</span></label>
                        <textarea name="notes" id="notes" rows="4" maxlength="1000" class="form-control" placeholder="Landmarks, gate/unit access instructions, or anything the specialist should know?">{{ $notes }}</textarea>

                        <div class="d-flex justify-content-between flex-wrap gap-2 mt-4">
                            <a href="{{ route('account.booking.home.schedule', array_merge(['service' => $service->id, 'date' => $date], $address)) }}" class="nx-btn nx-btn-outline">
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
