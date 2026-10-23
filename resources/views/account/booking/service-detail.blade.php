{{-- Branch Booking — Step 2: Service details (Phase 3A). Real Service
     record only (BookingController@serviceShow 404s for missing/inactive
     ids). No detail here is invented — name/description/price/duration/
     category all come straight from the services table.

     Problem #4: the same visit can carry EXTRA services. The main service
     keeps every existing behaviour (packages with variants included); an
     extra is always a regular, non-package branch service. Rows are only
     collected here — store() re-resolves every price and validates every
     service/personnel choice server-side. --}}
@extends('layouts.public')

@section('title', 'Book — ' . $service->name)

@section('content')
<section class="nx-booking-floating-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">
                <div class="nx-booking-panel">
                    <div class="nx-booking-panel-header">
                        <div>
                            <p class="nx-eyebrow mb-1">Branch Booking</p>
                            <h1 class="h5 mb-0">Service Details</h1>
                        </div>
                        @include('partials.booking.progress-compact', ['step' => 1])
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
                            <p class="nx-text-secondary mb-2">Down payment: {{ $service->down_payment !== null ? '₱'.number_format((float) $service->down_payment, 2) : 'none' }}</p>
                            @if($service->description)
                                <p class="mb-0">{{ $service->description }}</p>
                            @endif
                        </div>
                    </div>

                    <hr class="my-4" style="border-color: var(--nx-border);">

                    <form method="GET" action="{{ route('account.booking.branch.schedule') }}" id="serviceForm">
                        <input type="hidden" name="service" value="{{ $service->id }}">

                        @if($service->variants->isNotEmpty())
                            {{-- Package service: the client must pick a tier; its
                                 price (not the base services.price) is what gets
                                 charged. Continue carries the chosen variant id. --}}
                            <label class="form-label nx-eyebrow mb-2">Package Option</label>
                            <div class="row g-2 mb-4">
                                @foreach($service->variants as $option)
                                    <div class="col-sm-4">
                                        {{-- No hardcoded is-selected here: the stylesheet highlights
                                             the chosen card via .nx-select-card:has(input:checked),
                                             and the first radio stays checked so a tier is
                                             preselected (and highlighted) on load. --}}
                                        <label class="nx-select-card d-flex flex-column gap-1">
                                            <input type="radio" name="variant" value="{{ $option->id }}" {{ $loop->first ? 'checked' : '' }}>
                                            <span class="fw-semibold">{{ $option->label }}</span>
                                            <small class="nx-text-secondary">₱{{ number_format($option->price, 2) }}@if($option->guestLabel()) &middot; {{ $option->guestLabel() }}@endif</small>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <a href="{{ route('account.booking.branch.service') }}" class="nx-btn nx-btn-outline"
                               data-bs-toggle="modal" data-bs-target="#bookServiceModal">
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
