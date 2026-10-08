{{-- Home Service Booking — Step 1: Service selection (Phase 3B). Real,
     active, Home-Service-eligible Service records only
     (BookingController@homeServiceIndex — services.service_location_type in
     [home, both], App\Enums\ServiceLocationType). --}}
@extends('layouts.public')

@php($homeSteps = ['Service', 'Address', 'Date & Time', 'Details', 'Review', 'Payment', 'Submitted'])

@section('title', 'Book Home Service — Select a Service')

@section('content')
<header class="nx-hero">
    <div class="container py-5">
        <p class="nx-eyebrow mb-2">Home Service</p>
        <h1 class="mb-0">Select a Service</h1>
    </div>
</header>

<section class="nx-section" style="background: var(--nx-bg-page); padding-top: 40px;">
    <div class="container">
        @include('partials.booking.progress', ['step' => 1, 'steps' => $homeSteps])

        @if($servicesByCategory->isEmpty())
            <div class="nx-card p-5 text-center">
                <p class="nx-text-secondary mb-0">No Home Service-eligible services are available right now. Please check back soon.</p>
            </div>
        @else
            @foreach($servicesByCategory as $category => $services)
                <div class="mb-5">
                    <h2 class="h5 mb-3">{{ $category }}</h2>
                    <div class="row g-3">
                        @foreach($services as $service)
                            <div class="col-md-6 col-lg-4">
                                <a href="{{ route('account.booking.home.service.show', $service->id) }}" class="nx-card d-block h-100">
                                    <div class="nx-card-media">
                                        @if($service->image_url)
                                            <img src="{{ $service->image_url }}" alt="{{ $service->name }}">
                                        @else
                                            <i class="bi bi-image fs-1 opacity-50"></i>
                                        @endif
                                    </div>
                                    <div class="nx-card-body">
                                        <h3 class="nx-card-title h6 mb-1">{{ $service->name }}</h3>
                                        <p class="small nx-text-secondary mb-2">{{ $service->duration_minutes }} mins</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="nx-card-price">₱{{ number_format($service->price, 2) }}</span>
                                            <small class="nx-text-accent fw-semibold">Select <i class="bi bi-arrow-right"></i></small>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</section>
@endsection
