{{-- Home Service Booking — Step 1b: Service details (Phase 3B). Real
     Service record only (BookingController@homeServiceShow 404s for
     missing/inactive/non-Home-eligible ids). --}}
@extends('layouts.public')

@php($homeSteps = ['Service', 'Address', 'Date & Time', 'Details', 'Review', 'Payment'])

@section('title', 'Book Home Service — ' . $service->name)

@section('content')
<section class="nx-booking-floating-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">
                <div class="nx-booking-panel">
                    <div class="nx-booking-panel-header">
                        <div>
                            <p class="nx-eyebrow mb-1">HOME SERVICE</p>
                            <h1 class="h5 mb-0">Service Details</h1>
                        </div>
                        @include('partials.booking.progress-compact', ['step' => 1, 'steps' => $homeSteps])
                    </div>

                    <div class="row g-4 align-items-center">
                        <div class="col-md-5">
                            <div class="nx-card-media" style="height: 220px; border-radius: var(--nx-radius-lg);">
                                @if($service->image_url)
                                    <img src="{{ $service->image_url }}" alt="{{ $service->name }}">
                                @else
                                    <i class="bi bi-image fs-1 opacity-50"></i>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-7">
                            <span class="nx-badge mb-2">{{ $service->category }}</span>
                            <h2 class="h4 mb-2">{{ $service->name }}</h2>
                            <p class="h5 nx-text-accent mb-3">₱{{ number_format($service->price, 2) }}</p>
                            <p class="nx-text-secondary mb-2"><i class="bi bi-clock me-1"></i>{{ $service->duration_minutes }} minutes</p>
                            @if($service->down_payment !== null)
                                <p class="nx-text-secondary mb-2">Down payment: ₱{{ number_format((float) $service->down_payment, 2) }}</p>
                            @endif
                            @if($service->description)
                                <p class="mb-0">{{ $service->description }}</p>
                            @endif
                        </div>
                    </div>

                    <hr class="my-4" style="border-color: var(--nx-border);">

                    <form method="GET" action="{{ route('account.booking.home.address') }}" id="serviceForm">
                        <input type="hidden" name="service" value="{{ $service->id }}">

                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <a href="{{ route('landing') }}" class="nx-btn nx-btn-outline">
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
