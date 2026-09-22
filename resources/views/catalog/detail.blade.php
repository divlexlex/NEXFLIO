@extends('layouts.public')

@section('title', $title)

@section('content')
<section class="py-4 py-lg-5">
    <div class="container">
        <a href="{{ route('landing') }}" class="nx-btn nx-btn-outline mb-4" style="padding: 8px 16px; font-size: 13px;">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>

        <div class="row g-4 align-items-center">
            {{-- IMAGE --}}
            <div class="col-lg-6">
                @if(!empty($imageUrl))
                    <img src="{{ $imageUrl }}" alt="{{ $title }}" class="img-fluid w-100"
                         style="max-height: 460px; object-fit: cover; border-radius: var(--nx-radius-lg);">
                @else
                    <div class="nx-hero-media d-flex align-items-center justify-content-center text-white w-100"
                         style="height: 340px;">
                        <i class="bi bi-image display-3 opacity-50"></i>
                    </div>
                @endif
            </div>

            {{-- DETAILS --}}
            <div class="col-lg-6">
                <span class="nx-badge mb-2">
                    {{ ucfirst($category ?? $type) }}
                </span>
                <h1 class="mb-2">{{ $title }}</h1>
                @if($price !== null)
                    <p class="display-6 nx-text-accent mb-3">₱{{ number_format($price, 2) }}</p>
                @endif

                @if(!empty($meta))
                    <p class="nx-text-secondary mb-3"><i class="bi bi-info-circle me-1"></i>{{ $meta }}</p>
                @endif

                @if(!empty($description))
                    <p class="mb-4" style="max-width: 520px;">{{ $description }}</p>
                @endif

                @php
                    // Real, server-enforced eligibility (App\Enums\ServiceLocationType)
                    // — not a category-string guess. Absent for promos, which have no
                    // location concept and always fall back to the booking chooser.
                    $bookingRoute = match($type === 'service' ? ($locationType ?? null) : null) {
                        'branch' => route('account.booking.branch.schedule', ['service' => $id]),
                        'home' => route('account.booking.home.address', ['service' => $id]),
                        'both' => route('account.booking.location', ['service' => $id]),
                        default => route('account.booking.start'),
                    };
                    $bookingRoutePath = match($type === 'service' ? ($locationType ?? null) : null) {
                        'branch' => route('account.booking.branch.schedule', ['service' => $id], false),
                        'home' => route('account.booking.home.address', ['service' => $id], false),
                        'both' => route('account.booking.location', ['service' => $id], false),
                        default => null,
                    };
                @endphp
                @auth
                    @if(auth()->user()->isClient())
                        {{-- Authenticated Client: straight into the real Website
                             Booking Flow — never the Guest "Get the app" modal.
                             A real, active Service skips straight past its own
                             wizard's Service step(s) since this page already
                             showed everything they'd show. Promos fall back to
                             the booking type chooser (no real Promo→Service
                             booking link yet). --}}
                        <a href="{{ $bookingRoute }}" class="nx-btn nx-btn-primary"
                           @unless($bookingRoutePath) data-bs-toggle="modal" data-bs-target="#bookServiceModal" @endunless>
                            <i class="bi bi-calendar-check me-2"></i>{{ $type === 'promo' ? 'Reserve Now' : 'Book Now' }}
                        </a>
                    @endif
                @else
                    {{-- Guest: same "Get the app" / "Continue on Website" modal
                         as everywhere else. For a real bookable service, the
                         modal's login link is customized (data-booking-redirect,
                         see layouts.public) to return the Guest straight into
                         that service's booking flow after signing in — not just
                         back to this detail page — so the selected service is
                         never lost across the login redirect. --}}
                    <button type="button" class="nx-btn nx-btn-primary" data-bs-toggle="modal" data-bs-target="#getAppModal"
                            @if($bookingRoutePath)
                                {{-- Relative path, not the default absolute route() —
                                     AuthController::isSafeRedirectPath() only accepts
                                     paths starting with "/" (no host), same shape as
                                     request()->getRequestUri() everywhere else this
                                     redirect param is used. --}}
                                data-booking-redirect="{{ $bookingRoutePath }}"
                            @endif>
                        <i class="bi bi-calendar-check me-2"></i>{{ $cta }}
                    </button>
                @endauth
            </div>
        </div>
    </div>
</section>
@endsection
