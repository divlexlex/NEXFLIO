{{-- Booking type entry — the no-JS / direct-URL fallback for the
     "Book an Appointment" modal (partials/booking/service-picker-modal).
     Both Branch Booking (Phase 3A) and Home Service Booking (Phase 3B) are
     real, working flows, offered side by side here and in the modal. --}}
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
                        <a href="{{ route('account.booking.home.service') }}" class="nx-select-card h-100 d-flex flex-column" style="padding: 28px;">
                            <div class="nx-value-icon mb-3"><i class="bi bi-house-heart"></i></div>
                            <h2 class="h5 mb-2">Home Service</h2>
                            <p class="nx-text-secondary mb-3">Have a specialist come to you. Choose a service, tell us where to go, pick a date and time, and reserve your visit.</p>
                            <span class="nx-btn nx-btn-primary mt-auto align-self-start">Continue <i class="bi bi-arrow-right ms-1"></i></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
