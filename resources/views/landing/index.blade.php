@extends('layouts.public')

@section('title', 'Perfect Nails Wellness and Aesthetics')

@section('content')
{{-- HERO --}}
<header class="py-5">
    <div class="container py-5 text-center">
        <p class="text-uppercase text-gold small mb-2" style="letter-spacing: .25em;">Wellness &amp; Aesthetics</p>
        <h1 class="display-4 fw-bold mb-3">Your best skin,<br>created by science.</h1>
        <p class="lead mb-4 mx-auto" style="max-width: 540px;">
            Nails, massage, and aesthetics services by certified specialists.
            Book from your phone, pay securely, and walk in relaxed.
        </p>
        <div class="d-flex justify-content-center gap-2">
            <a href="#app" class="btn btn-spa btn-lg px-4">Book on the App</a>
            <a href="#services" class="btn btn-outline-spa btn-lg px-4">Browse Services</a>
        </div>
    </div>
</header>

{{-- SERVICES --}}
<section id="services" class="py-5 bg-white border-top border-bottom" style="border-color: var(--spa-tan) !important;">
    <div class="container">
        <h2 class="text-center mb-2">Our Services</h2>
        <p class="text-center text-muted mb-4">Whole-hearted care, per session pricing.</p>

        @if($servicesByCategory->isEmpty())
            <p class="text-center text-muted">Our service menu is being polished — check back soon.</p>
        @else
            <ul class="nav nav-pills justify-content-center mb-4" role="tablist">
                @foreach($servicesByCategory as $category => $services)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $loop->first ? 'active' : '' }}"
                                data-bs-toggle="pill" data-bs-target="#cat-{{ $loop->index }}"
                                type="button" role="tab"
                                style="{{ $loop->first ? 'background: var(--spa-espresso);' : 'color: var(--spa-espresso);' }}">
                            {{ $category }}
                        </button>
                    </li>
                @endforeach
            </ul>
            <div class="tab-content">
                @foreach($servicesByCategory as $category => $services)
                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="cat-{{ $loop->index }}" role="tabpanel">
                        <div class="row g-3">
                            @foreach($services as $service)
                                <div class="col-md-6 col-lg-4">
                                    <div class="card-spa p-3 h-100 d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h3 class="h6 mb-1">{{ $service->name }}</h3>
                                            <span class="text-gold fw-bold">₱{{ number_format($service->price, 2) }}</span>
                                        </div>
                                        <p class="small text-muted mb-2 flex-grow-1">{{ $service->description }}</p>
                                        <small class="text-muted"><i class="bi bi-clock me-1"></i>{{ $service->duration_minutes }} mins</small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- PROMOS --}}
@if($promos->isNotEmpty())
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-4">Current Promos</h2>
        <div class="row g-3 justify-content-center">
            @foreach($promos as $promo)
                <div class="col-md-4">
                    <div class="card-spa p-4 h-100 text-center">
                        <h3 class="h5">{{ $promo->title }}</h3>
                        <p class="display-6 text-gold mb-1">₱{{ number_format($promo->price, 2) }}</p>
                        @if($promo->service)
                            <small class="text-muted">{{ $promo->service->name }}</small>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ABOUT --}}
<section id="about" class="py-5 bg-white border-top" style="border-color: var(--spa-tan) !important;">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <h2>About Perfect Nails</h2>
                <p>
                    Perfect Nails Wellness and Aesthetics is a full-service spa offering nail care,
                    therapeutic massage, and aesthetic treatments. Every session is delivered by
                    trained personnel using tracked, quality-controlled products.
                </p>
                <p class="mb-0">
                    Powered by <strong>NEXFLIO</strong> — our business management system with
                    AI-supported analytics — so bookings, payments, and service quality are
                    handled with care from tap to treatment.
                </p>
            </div>
            <div class="col-lg-6">
                <div class="card-spa p-4">
                    <div class="row text-center g-3">
                        <div class="col-4">
                            <div class="h3 text-gold mb-0">3</div>
                            <small class="text-muted">Service Lines</small>
                        </div>
                        <div class="col-4">
                            <div class="h3 text-gold mb-0">100%</div>
                            <small class="text-muted">Verified Payments</small>
                        </div>
                        <div class="col-4">
                            <div class="h3 text-gold mb-0">In-App</div>
                            <small class="text-muted">Booking &amp; Tracking</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- APP DOWNLOAD --}}
<section id="app" class="py-5">
    <div class="container text-center">
        <h2 class="mb-2">Book from the Perfect Nails app</h2>
        <p class="text-muted mb-4 mx-auto" style="max-width: 480px;">
            Browse the menu, pick your specialist, choose a slot, and upload your proof of
            payment — your booking is confirmed once our manager verifies it.
        </p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <a href="#" class="btn btn-spa btn-lg px-4"><i class="bi bi-android2 me-2"></i>Download for Android</a>
            <a href="#" class="btn btn-outline-spa btn-lg px-4"><i class="bi bi-apple me-2"></i>Coming to iOS</a>
        </div>
    </div>
</section>

{{-- CONTACT --}}
<section id="contact" class="py-5 bg-white border-top" style="border-color: var(--spa-tan) !important;">
    <div class="container">
        <h2 class="text-center mb-4">Contact Us</h2>
        <div class="row justify-content-center g-3 text-center">
            <div class="col-md-3">
                <div class="card-spa p-3 h-100">
                    <i class="bi bi-geo-alt fs-3 text-gold"></i>
                    <p class="small mb-0 mt-2">Visit us at our branch — walk-ins welcome.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-spa p-3 h-100">
                    <i class="bi bi-clock fs-3 text-gold"></i>
                    <p class="small mb-0 mt-2">Open daily<br>10:00 AM – 8:00 PM</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card-spa p-3 h-100">
                    <i class="bi bi-envelope fs-3 text-gold"></i>
                    <p class="small mb-0 mt-2">hello@perfectnails.example<br>for inquiries &amp; partnerships</p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
