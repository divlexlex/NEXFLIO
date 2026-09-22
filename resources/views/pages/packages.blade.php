{{-- Guest Packages page. TEMPORARY frontend-only content — see
     config/packages.php for the source and future Admin-migration note.
     Expects: $featured, $items, $addOns, $tagline, $disclaimer. --}}
@extends('layouts.public')

@section('title', 'Packages')

@section('content')

{{-- HERO --}}
<header class="nx-hero">
    <div class="container py-5 text-center">
        <div class="mx-auto" style="max-width: 640px;">
            <p class="nx-eyebrow mb-3">Packages</p>
            <h1 class="mb-3">Bundled for longer stays</h1>
            <p class="nx-text-secondary mb-0" style="font-size: 16.5px;">{{ $tagline }}</p>
        </div>
    </div>
</header>

{{-- FEATURED PACKAGE --}}
<section class="nx-section" style="background: var(--nx-bg-surface);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="nx-package-featured">
                    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-2">
                        <div>
                            @if(!empty($featured['badge']))
                                <span class="nx-badge mb-3">{{ $featured['badge'] }}</span>
                            @endif
                            <h2 class="nx-font-display mb-0" style="color:#fff; font-size: 30px;">{{ $featured['name'] }}</h2>
                        </div>
                    </div>
                    <ul class="nx-package-checklist mb-0" style="color: rgba(255,255,255,.75);">
                        @foreach($featured['inclusions'] as $inclusion)
                            <li>&check; {{ $inclusion }}</li>
                        @endforeach
                    </ul>
                    <div class="nx-tier-list">
                        @foreach($featured['tiers'] as $tier)
                            <div class="nx-tier-row">
                                <span class="nx-tier-label">{{ $tier['label'] }}</span>
                                <span class="nx-tier-price">&#8369;{{ number_format($tier['price'], 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                    @include('partials.booking-cta', ['label' => 'Reserve This Package', 'class' => 'w-100 justify-content-center'])
                </div>
            </div>
        </div>
    </div>
</section>

{{-- PACKAGE CARDS --}}
<section class="nx-section" style="background: var(--nx-bg-page);">
    <div class="container">
        <div class="text-center mb-4">
            <p class="nx-eyebrow mb-2">More Packages</p>
            <h2 class="nx-section-title">Choose your ritual</h2>
        </div>
        <div class="row g-4">
            @foreach($items as $package)
                <div class="col-md-6">
                    <div class="nx-package-card h-100 d-flex flex-column">
                        <p class="h5 nx-font-display mb-2">{{ $package['name'] }}</p>
                        <ul class="nx-package-checklist mb-0">
                            @foreach($package['inclusions'] as $inclusion)
                                <li>&check; {{ $inclusion }}</li>
                            @endforeach
                        </ul>
                        <div class="nx-tier-list flex-grow-1">
                            @foreach($package['tiers'] as $tier)
                                <div class="nx-tier-row">
                                    <span class="nx-tier-label">{{ $tier['label'] }}</span>
                                    <span class="nx-tier-price">&#8369;{{ number_format($tier['price'], 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                        @include('partials.booking-cta', ['label' => 'Reserve This Package', 'class' => 'w-100 justify-content-center mt-2'])
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ADD-ONS --}}
@if(!empty($addOns))
<section class="nx-section" style="background: var(--nx-bg-surface);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="text-center mb-4">
                    <p class="nx-eyebrow mb-2">Available Add-ons</p>
                    <h2 class="nx-section-title" style="font-size: 30px;">Extend your treatment</h2>
                </div>
                <div class="nx-card p-4">
                    @foreach($addOns as $addOn)
                        <div class="nx-addon-row">
                            <span>{{ $addOn['name'] }}</span>
                            <span class="nx-addon-price">&#8369;{{ number_format($addOn['price'], 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @if(!empty($disclaimer))
            <p class="text-center nx-text-secondary small mt-4 mb-0">{{ $disclaimer }}</p>
        @endif
    </div>
</section>
@endif

{{-- CTA BANNER --}}
<section class="pb-5" style="background: var(--nx-bg-page); padding-top: 24px;">
    <div class="container">
        <div class="nx-cta-banner d-flex align-items-center justify-content-between flex-wrap gap-4">
            <div>
                <h2 class="mb-2" style="color:#fff; font-size: 28px;">Ready to treat yourself?</h2>
                <p class="mb-0" style="max-width: 440px; opacity: .85;">Browse the full service menu or reserve one of these packages.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @include('partials.booking-cta', ['label' => 'Reserve Now'])
                <a href="{{ route('services') }}" class="nx-btn nx-btn-outline" style="border-color:#fff; color:#fff;">View All Services</a>
            </div>
        </div>
    </div>
</section>
@endsection
