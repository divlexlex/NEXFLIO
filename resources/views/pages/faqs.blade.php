{{-- Guest FAQs page. Content sourced from config/faqs.php, which mirrors
     the same verified copy already live on Home — see that file's doc
     comment. No policy claim here that isn't already approved and live
     elsewhere on the site. Expects: $faqs. --}}
@extends('layouts.public')

@section('title', 'FAQs')

@section('content')

{{-- HERO --}}
<header class="nx-hero">
    <div class="container py-5 text-center">
        <div class="mx-auto" style="max-width: 640px;">
            <p class="nx-eyebrow mb-3">Frequently Asked</p>
            <h1 class="mb-3">Before you book</h1>
            <p class="nx-text-secondary mb-0" style="font-size: 16.5px;">
                Still unsure? Reach out and our staff will answer any service questions.
            </p>
        </div>
    </div>
</header>

<section class="nx-section" style="background: var(--nx-bg-page);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                @if(empty($faqs))
                    <p class="text-center nx-text-secondary">Our FAQ page is being put together — check back soon.</p>
                @else
                    <div class="accordion d-flex flex-column gap-3" id="faqPageAccordion">
                        @foreach($faqs as $i => $faq)
                            <div class="nx-faq-item">
                                <a class="nx-faq-question" data-bs-toggle="collapse" href="#faqp-{{ $i }}" role="button" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}">
                                    <span>
                                        <span class="nx-badge me-2" style="font-size: 10px; padding: 3px 9px;">{{ $faq['category'] }}</span>
                                        {{ $faq['question'] }}
                                    </span>
                                    <span class="nx-faq-icon">{{ $i === 0 ? '−' : '+' }}</span>
                                </a>
                                <div class="collapse {{ $i === 0 ? 'show' : '' }}" id="faqp-{{ $i }}" data-bs-parent="#faqPageAccordion">
                                    <p class="nx-faq-answer mb-0">{{ $faq['answer'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

{{-- CTA BANNER --}}
<section class="pb-5">
    <div class="container">
        <div class="nx-cta-banner d-flex align-items-center justify-content-between flex-wrap gap-4">
            <div>
                <h2 class="mb-2" style="color:#fff; font-size: 28px;">Still have a question?</h2>
                <p class="mb-0" style="max-width: 440px; opacity: .85;">Reach out and our staff will be happy to help.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('contact') }}" class="nx-btn nx-btn-outline" style="border-color:#fff; color:#fff;">Contact Us</a>
                @include('partials.booking-cta', ['label' => 'Book an Appointment'])
            </div>
        </div>
    </div>
</section>
@endsection
