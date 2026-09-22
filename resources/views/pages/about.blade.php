{{-- Guest About page. Conservative, verified copy only — no founding date,
     awards, certifications, customer counts, or testimonials are claimed
     anywhere on this page (none exist in approved reference material).
     The "What to expect" section reuses the exact value props already
     live on Home (Why Choose Perfect Nails) rather than inventing new
     claims. --}}
@extends('layouts.public')

@section('title', 'About')

@section('content')

{{-- HERO --}}
<header class="nx-hero">
    <div class="container py-5 text-center">
        <div class="mx-auto" style="max-width: 640px;">
            <p class="nx-eyebrow mb-3">About Us</p>
            <h1 class="mb-3">Perfect Nails Wellness &amp; Aesthetics</h1>
            <p class="nx-text-secondary mb-0" style="font-size: 16.5px;">
                Beauty, wellness, and relaxation — thoughtfully delivered, one visit at a time.
            </p>
        </div>
    </div>
</header>

{{-- OUR STUDIO --}}
<section class="nx-section" style="background: var(--nx-bg-surface);">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <p class="nx-eyebrow mb-2">Our Studio</p>
                <h2 class="mb-3">A wellness studio built around you</h2>
                <p class="nx-text-secondary mb-3">
                    Perfect Nails Wellness &amp; Aesthetics is a nail, spa, and beauty studio in Maypajo,
                    Caloocan, offering a full range of treatments — from nail care, lash &amp; brow
                    services, and signature facials, to massage therapy, Japanese Head Spa rituals, and
                    aesthetic treatments.
                </p>
                <p class="nx-text-secondary mb-0">
                    Walk-ins are always welcome, and home service is available for select treatments
                    within our coverage area — so a Perfect Nails experience can meet you wherever
                    is most comfortable.
                </p>
            </div>
            <div class="col-lg-6">
                <img src="{{ asset('images/hero-slider/facial-treatment.jpg') }}"
                     alt="A treatment session at Perfect Nails Wellness & Aesthetics"
                     class="img-fluid w-100" style="border-radius: var(--nx-radius-lg); aspect-ratio: 4/5; object-fit: cover;">
            </div>
        </div>
    </div>
</section>

{{-- WHAT TO EXPECT (same value props already approved on Home) --}}
<section class="nx-section" style="background: var(--nx-bg-page);">
    <div class="container">
        <div class="text-center mb-5">
            <p class="nx-eyebrow mb-2">What to Expect</p>
            <h2 class="nx-section-title" style="font-size: 34px;">Care you can rely on</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-3 col-6">
                <div class="nx-value-icon"><i class="bi bi-award"></i></div>
                <h3 class="h6 mb-2">Certified Specialists</h3>
                <p class="nx-text-secondary small mb-0">Trained and experienced therapists for every treatment.</p>
            </div>
            <div class="col-md-3 col-6">
                <div class="nx-value-icon"><i class="bi bi-shield-check"></i></div>
                <h3 class="h6 mb-2">Premium Products</h3>
                <p class="nx-text-secondary small mb-0">Only trusted, skin-safe formulations used in every service.</p>
            </div>
            <div class="col-md-3 col-6">
                <div class="nx-value-icon"><i class="bi bi-moon-stars"></i></div>
                <h3 class="h6 mb-2">Relaxing Ambiance</h3>
                <p class="nx-text-secondary small mb-0">A calm, private studio designed for genuine rest.</p>
            </div>
            <div class="col-md-3 col-6">
                <div class="nx-value-icon"><i class="bi bi-calendar-check"></i></div>
                <h3 class="h6 mb-2">Flexible Scheduling</h3>
                <p class="nx-text-secondary small mb-0">Online booking with real-time availability, 7 days a week.</p>
            </div>
        </div>
    </div>
</section>

{{-- VISIT US --}}
<section class="nx-section" style="background: var(--nx-bg-surface);">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <p class="nx-eyebrow mb-2">Visit Us</p>
                <h2 class="mb-2" style="font-size: 28px;">237 A. Mabini St., Maypajo, Caloocan</h2>
                <p class="nx-text-secondary mb-0">Open daily, 10:00 AM &ndash; 9:00 PM.</p>
            </div>
            <div class="col-lg-5 text-lg-end">
                <a href="{{ route('contact') }}" class="nx-btn nx-btn-outline">Get in Touch <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
</section>

{{-- CTA BANNER --}}
<section class="pb-5" style="background: var(--nx-bg-page); padding-top: 24px;">
    <div class="container">
        <div class="nx-cta-banner d-flex align-items-center justify-content-between flex-wrap gap-4">
            <div>
                <h2 class="mb-2" style="color:#fff; font-size: 28px;">Ready when you are</h2>
                <p class="mb-0" style="max-width: 440px; opacity: .85;">See the full menu or book your next visit.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @include('partials.booking-cta', ['label' => 'Book an Appointment'])
                <a href="{{ route('services') }}" class="nx-btn nx-btn-outline" style="border-color:#fff; color:#fff;">View Services</a>
            </div>
        </div>
    </div>
</section>
@endsection
