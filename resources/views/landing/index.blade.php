@extends('layouts.public')

@section('title', 'Perfect Nails Wellness and Aesthetics')

@section('content')

{{-- HERO --}}
<header class="nx-hero">
    <div class="container py-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <p class="nx-eyebrow mb-3">Perfect Nails Wellness &amp; Aesthetics</p>
                <h1 class="mb-3">Beauty, wellness, and relaxation in one perfect experience.</h1>
                <p class="nx-text-secondary mb-4" style="font-size: 16.5px; max-width: 520px;">
                    From Japanese Head Spa rituals to signature facials, nail artistry, and massage
                    therapy — experience premium self-care crafted around you, at our Caloocan wellness studio.
                </p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="{{ route('services') }}" class="nx-btn nx-btn-outline">View Services</a>
                    @auth
                        <a href="{{ route('account.booking.start') }}" class="nx-btn nx-btn-primary"
                           data-bs-toggle="modal" data-bs-target="#bookServiceModal">Book an Appointment</a>
                    @else
                        <button type="button" class="nx-btn nx-btn-primary" data-bs-toggle="modal" data-bs-target="#getAppModal">
                            Book an Appointment
                        </button>
                    @endauth
                </div>
            </div>
            <div class="col-lg-6">
                @if(!empty($heroSlides))
                    @include('partials.hero-slider', ['slides' => $heroSlides])
                @else
                    <div class="nx-hero-media w-100"></div>
                @endif
            </div>
        </div>
    </div>
</header>

{{-- SPECIAL OFFERS — real, Admin-managed Promos only, no fake fallback
     content; the whole section simply doesn't render when there are none
     right now. Distinct from the "Packages" section further down (static
     Website copy, config/packages.php) — this one is always live Promo data,
     with "See all offers" going to the full /offers page. --}}
@if($promos->isNotEmpty())
<section class="nx-section py-4">
    <div class="container">
        <div class="nx-section-header mb-4">
            <div>
                <p class="nx-eyebrow mb-2">Limited Time</p>
                <h2 class="nx-section-title mb-0">Special Offers</h2>
            </div>
            <a href="{{ route('offers') }}" class="nx-section-link">See all offers &rarr;</a>
        </div>
        @include('partials.offers-section', ['promos' => $promos, 'limit' => 4])
    </div>
</section>
@endif

{{-- VISIT & BOOKING HIGHLIGHTS (redesigned from the old plain info strip) —
     all three facts (walk-ins accepted, home service available, 10AM–9PM daily
     hours) already match what's verified on this same page's own Visit/Contact
     section and config/contact_info.php; supporting lines add no new policy
     claim (Home Service's line condenses the same wording already used in the
     FAQ below). --}}
<section class="py-4">
    <div class="container">
        <div class="nx-visit-highlights">
            <div class="nx-visit-block">
                <span class="nx-visit-icon"><i class="bi bi-person-walking"></i></span>
                <div>
                    <p class="nx-visit-eyebrow">Walk-ins</p>
                    <p class="nx-visit-main">Walk-ins Welcome</p>
                    <p class="nx-visit-sub">Subject to availability</p>
                </div>
            </div>
            <div class="nx-visit-divider"></div>
            <div class="nx-visit-block">
                <span class="nx-visit-icon"><i class="bi bi-house-heart"></i></span>
                <div>
                    <p class="nx-visit-eyebrow">Home Service</p>
                    <p class="nx-visit-main">Home Service Available</p>
                    <p class="nx-visit-sub">Coverage confirmed at booking</p>
                </div>
            </div>
            <div class="nx-visit-divider"></div>
            <div class="nx-visit-block">
                <span class="nx-visit-icon"><i class="bi bi-clock"></i></span>
                <div>
                    <p class="nx-visit-eyebrow">Hours</p>
                    <p class="nx-visit-main">10AM&ndash;9PM</p>
                    <p class="nx-visit-sub">Open daily</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- POPULAR TREATMENTS --}}
@php($popular = \App\Models\Service::where('status', 'active')->orderBy('price')->take(6)->get())
@if($popular->isNotEmpty())
<section class="nx-section" style="background: var(--nx-bg-surface-muted);">
    <div class="container">
        <div class="mb-4">
            <h2 class="nx-section-title mb-2" style="font-size: 34px;">Popular Treatments</h2>
            <p class="nx-text-secondary mb-0">Client favorites across nails, lashes, brows, and body care.</p>
        </div>
        <div class="d-flex flex-wrap gap-3">
            @foreach($popular as $service)
                <a href="{{ route('catalog.service', $service->id) }}" class="nx-pill">
                    {{ $service->name }} &middot; ₱{{ number_format($service->price, 0) }}
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- OUR WORK — a real-photo preview pulled from the same source as the full
     /gallery page (config/gallery.php), reshuffled each load. Distinct from
     Popular Treatments (pricing) and Recommended for You (bookable items) —
     this section is purely visual, proof-of-work for Guests still deciding. --}}
@php($galleryPreview = collect(config('gallery.categories', []))->pluck('images')->flatten(1)->shuffle()->take(6))
@if($galleryPreview->isNotEmpty())
<section class="nx-section" style="background: var(--nx-bg-surface);">
    <div class="container">
        <div class="nx-section-header mb-4">
            <div>
                <p class="nx-eyebrow mb-2">Our Work</p>
                <h2 class="nx-section-title mb-0">A closer look at Perfect Nails</h2>
            </div>
            <a href="{{ route('gallery') }}" class="nx-section-link">See full gallery &rarr;</a>
        </div>
        <div class="nx-gallery-grid">
            @foreach($galleryPreview as $image)
                <a href="{{ route('gallery') }}" class="nx-gallery-item">
                    <img src="{{ asset($image['file']) }}" alt="{{ $image['alt'] }}" loading="lazy">
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- RECOMMENDED FOR YOU (dynamic random mix, reshuffles each load) --}}
@if($recommendations->isNotEmpty())
<section class="nx-section" style="background: var(--nx-bg-page);">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="nx-section-title mb-2" style="font-size: 34px;">Recommended for You</h2>
            <p class="nx-text-secondary mb-0">A fresh mix every visit — tap anything to see details.</p>
        </div>
        <div class="row g-3">
            @foreach($recommendations as $rec)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('partials.item-card', [
                        'url' => route('catalog.' . $rec['type'], $rec['id']),
                        'imageUrl' => $rec['image_url'],
                        'title' => $rec['title'],
                        'price' => $rec['price'],
                        'subtitle' => $rec['subtitle'],
                        'badge' => ucfirst($rec['type']),
                        'ctaText' => 'View',
                    ])
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- WHY CHOOSE US --}}
<section id="about" class="nx-section" style="background: var(--nx-bg-surface);">
    <div class="container">
        <div class="mb-5">
            <h2 class="nx-section-title mb-2" style="font-size: 34px;">Why Choose Perfect Nails</h2>
            <p class="nx-text-secondary mb-0">Premium care, trained specialists, and a serene studio environment.</p>
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

{{-- VISIT / CONTACT --}}
<section id="contact" class="nx-section" style="background: var(--nx-bg-page);">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <a href="https://maps.google.com/?q=237+A.+Mabini+St.,+Maypajo,+Caloocan" target="_blank" rel="noopener" class="nx-map-placeholder w-100">
                    <span class="nx-map-dot"></span>
                    <span>Map &mdash; 237 A. Mabini St., Maypajo, Caloocan</span>
                </a>
            </div>
            <div class="col-lg-6">
                <h2 class="mb-4">Visit Our Branch</h2>
                <div class="mb-4">
                    <p class="nx-eyebrow mb-1">Address</p>
                    <p class="mb-0">Perfect Nails Wellness &amp; Aesthetics</p>
                    <p class="mb-0">237 A. Mabini St., Maypajo, Caloocan</p>
                </div>
                <div class="mb-4">
                    <p class="nx-eyebrow mb-1">Business Hours</p>
                    <p class="mb-0">Monday &ndash; Sunday: 10:00 AM &ndash; 9:00 PM</p>
                </div>
                <div class="mb-4">
                    <p class="nx-eyebrow mb-1">Contact</p>
                    <p class="mb-0">0905 556 3955</p>
                    <p class="mb-0">0917 558 2122</p>
                </div>
                <div>
                    <p class="nx-eyebrow mb-2">We Accept</p>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="nx-pill"><i class="bi bi-credit-card-2-front me-1"></i>Cards</span>
                        <span class="nx-pill"><i class="bi bi-qr-code me-1"></i>QRPh</span>
                        <span class="nx-pill"><i class="bi bi-cash-stack me-1"></i>Cash</span>
                        <span class="nx-pill"><i class="bi bi-wallet2 me-1"></i>GCash</span>
                        <span class="nx-pill"><i class="bi bi-wallet2 me-1"></i>Maya</span>
                        <span class="nx-pill"><i class="bi bi-bank me-1"></i>GoTyme</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- FAQ --}}
<section id="faqs" class="nx-section" style="background: var(--nx-bg-surface);">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <p class="nx-eyebrow mb-2">Frequently Asked</p>
                <h2 class="mb-3">Before you book</h2>
                <p class="nx-text-secondary mb-0">Still unsure? Reach out and our staff will answer any service questions.</p>
            </div>
            <div class="col-lg-8">
                <div class="accordion d-flex flex-column gap-3" id="faqAccordion">
                    @php($faqs = [
                        ['q' => 'Do I need to pay before my appointment?', 'a' => 'A ₱200 reservation fee secures your slot. Upload your proof of payment in the booking portal and the branch verifies it, usually within the hour. The balance is settled at check-out.'],
                        ['q' => 'What happens if I arrive late?', 'a' => 'A short grace period applies per service; arriving beyond it may require rescheduling depending on the day\'s bookings. Message the branch as soon as you know you\'ll be late.'],
                        ['q' => 'Can I request a specific therapist?', 'a' => 'Yes — preferred staff can be requested during booking, subject to their availability on your selected date and time.'],
                        ['q' => 'How do I cancel or reschedule?', 'a' => 'Manage your booking from the mobile app up to the cutoff shown on your appointment details.'],
                        ['q' => 'Is home service available in my area?', 'a' => 'Home service coverage depends on your location. Availability is confirmed in the app when you book a home-service treatment.'],
                    ])
                    @foreach($faqs as $i => $faq)
                        <div class="nx-faq-item">
                            <a class="nx-faq-question" data-bs-toggle="collapse" href="#faq-{{ $i }}" role="button" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}">
                                <span>{{ $faq['q'] }}</span>
                                <span class="nx-faq-icon">{{ $i === 0 ? '−' : '+' }}</span>
                            </a>
                            <div class="collapse {{ $i === 0 ? 'show' : '' }}" id="faq-{{ $i }}" data-bs-parent="#faqAccordion">
                                <p class="nx-faq-answer mb-0">{{ $faq['a'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- CTA BANNER --}}
<section class="pb-5">
    <div class="container">
        <div class="nx-cta-banner d-flex align-items-center justify-content-between flex-wrap gap-4">
            <div>
                <h2 class="mb-2" style="color:#fff; font-size: 30px;">Ready when you are</h2>
                <p class="mb-0" style="max-width: 480px; opacity: .85;">
                    Book online in minutes — pick your specialist, choose a slot, and confirm your appointment.
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @auth
                    <a href="{{ route('account.booking.start') }}" class="nx-btn nx-btn-primary"
                       data-bs-toggle="modal" data-bs-target="#bookServiceModal">Book an Appointment</a>
                @else
                    <button type="button" class="nx-btn nx-btn-primary" data-bs-toggle="modal" data-bs-target="#getAppModal">
                        Book an Appointment
                    </button>
                @endauth
                <button type="button" class="nx-btn nx-btn-outline" style="border-color:#fff; color:#fff;" data-bs-toggle="modal" data-bs-target="#getAppModal">
                    Get the App
                </button>
            </div>
        </div>
    </div>
</section>
@endsection
