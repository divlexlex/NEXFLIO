{{-- Guest Contact page. Every fact here is sourced from config/contact_info.php,
     which mirrors the same verified info already live on Home/the footer —
     see that file's doc comment. No phone/email/address/hour is invented.
     No contact-form submission exists in the backend (see PageController@contact
     history / completion report) — this page surfaces the real, already-live
     channels (call, and the existing Dialogflow chat widget where configured)
     instead of a form that would silently do nothing. Expects: $info. --}}
@extends('layouts.public')

@section('title', 'Contact')

@section('content')

{{-- HERO --}}
<header class="nx-hero">
    <div class="container py-5 text-center">
        <div class="mx-auto" style="max-width: 640px;">
            <p class="nx-eyebrow mb-3">Contact</p>
            <h1 class="mb-3">Get in Touch</h1>
            <p class="nx-text-secondary mb-0" style="font-size: 16.5px;">
                Questions about a service or your visit? Reach us any of the ways below.
            </p>
        </div>
    </div>
</header>

<section class="nx-section" style="background: var(--nx-bg-surface);">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <a href="{{ $info['map_url'] }}" target="_blank" rel="noopener" class="nx-map-placeholder w-100">
                    <span class="nx-map-dot"></span>
                    <span>Map &mdash; {{ $info['address_line'] }}</span>
                </a>
            </div>
            <div class="col-lg-6">
                <div class="mb-4">
                    <p class="nx-eyebrow mb-1">Address</p>
                    <p class="mb-0">{{ $info['business_name'] }}</p>
                    <p class="mb-0">{{ $info['address_line'] }}</p>
                </div>
                <div class="mb-4">
                    <p class="nx-eyebrow mb-1">Business Hours</p>
                    <p class="mb-0">{{ $info['hours'] }}</p>
                </div>
                <div class="mb-4">
                    <p class="nx-eyebrow mb-1">Call Us</p>
                    @foreach($info['phones'] as $phone)
                        <p class="mb-0"><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="nx-text-accent text-decoration-none">{{ $phone }}</a></p>
                    @endforeach
                </div>
                @if(config('services.dialogflow.agent_id'))
                    <div>
                        <p class="nx-eyebrow mb-1">Chat With Us</p>
                        <p class="mb-0 nx-text-secondary">Use the chat bubble in the bottom corner of this page for a quick answer.</p>
                    </div>
                @endif
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
