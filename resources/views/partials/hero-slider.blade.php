{{-- Guest Home hero slider — TEMPORARY frontend-only imagery (see config/hero_slider.php).
     Bootstrap's built-in Carousel is reused here; no new JS library was added.
     Expects: $slides — a list of ['image' => public-relative path, 'alt' => string]. --}}
@php($multiple = count($slides) > 1)
<div id="heroSlider"
     class="carousel slide nx-hero-slider"
     @if($multiple) data-bs-ride="carousel" data-bs-interval="6000" data-bs-pause="hover" @endif
     role="region" aria-label="Perfect Nails treatment highlights" aria-roledescription="carousel">

    <div class="carousel-inner">
        @foreach($slides as $i => $slide)
            <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                <img src="{{ asset($slide['image']) }}"
                     class="d-block w-100"
                     alt="{{ $slide['alt'] }}"
                     loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
            </div>
        @endforeach
    </div>

    {{-- Sits under the controls/indicators (see z-index in website.css) so the images stay
         legible and share one warm palette even though the source photos don't match exactly. --}}
    <div class="nx-hero-slider-overlay" aria-hidden="true"></div>

    @if($multiple)
        <div class="carousel-indicators">
            @foreach($slides as $i => $slide)
                <button type="button" data-bs-target="#heroSlider" data-bs-slide-to="{{ $i }}"
                        class="{{ $i === 0 ? 'active' : '' }}"
                        aria-current="{{ $i === 0 ? 'true' : 'false' }}"
                        aria-label="Show slide {{ $i + 1 }}"></button>
            @endforeach
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroSlider" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous slide</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroSlider" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next slide</span>
        </button>
    @endif
</div>

@once
    @push('scripts')
        <script>
            // Respect prefers-reduced-motion: keep the slider visible but stop it
            // from auto-rotating; manual prev/next/indicator controls still work.
            (function () {
                var el = document.getElementById('heroSlider');
                if (!el || !window.bootstrap || !window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                var instance = bootstrap.Carousel.getOrCreateInstance(el);
                instance.pause();
            })();
        </script>
    @endpush
@endonce
