{{-- Branch vs Home Service choice for a service whose
     service_location_type is `both` (App\Enums\ServiceLocationType).
     Reached only for an authenticated Client — never the Guest modal — from
     a Book CTA that already knows the service; the selected service carries
     straight through to whichever flow the Client picks. --}}
@extends('layouts.public')

@section('title', 'Book — ' . $service->name)

@section('content')
<header class="nx-hero">
    <div class="container py-5">
        <p class="nx-eyebrow mb-2">{{ $service->name }}</p>
        <h1 class="mb-2">Where would you like this service?</h1>
        <p class="nx-text-secondary mb-0">This service is available both at the branch and as a Home Service.</p>
    </div>
</header>

<section class="nx-section" style="background: var(--nx-bg-page); padding-top: 48px;">
    <div class="container">
        <div class="row g-4 justify-content-center">
            <div class="col-md-5">
                <a href="{{ route('account.booking.branch.schedule', ['service' => $service->id]) }}" class="nx-select-card h-100 d-flex flex-column" style="padding: 28px;">
                    <div class="nx-value-icon mb-3"><i class="bi bi-shop"></i></div>
                    <h2 class="h5 mb-2">Visit Branch</h2>
                    <p class="nx-text-secondary mb-3">Visit our Maypajo studio for your treatment.</p>
                    <span class="nx-btn nx-btn-primary mt-auto align-self-start">Continue <i class="bi bi-arrow-right ms-1"></i></span>
                </a>
            </div>
            <div class="col-md-5">
                <a href="{{ route('account.booking.home.address', ['service' => $service->id]) }}" class="nx-select-card h-100 d-flex flex-column" style="padding: 28px;">
                    <div class="nx-value-icon mb-3"><i class="bi bi-house-heart"></i></div>
                    <h2 class="h5 mb-2">Home Service</h2>
                    <p class="nx-text-secondary mb-3">Have a specialist come to you.</p>
                    <span class="nx-btn nx-btn-primary mt-auto align-self-start">Continue <i class="bi bi-arrow-right ms-1"></i></span>
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
