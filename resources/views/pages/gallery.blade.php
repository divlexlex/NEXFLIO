{{-- Guest Gallery page. TEMPORARY frontend-only images — see
     config/gallery.php for the source and future Admin-migration note.
     Lightbox reuses Bootstrap's existing modal component (already loaded
     sitewide) — no new JS library. Expects: $categories. --}}
@extends('layouts.public')

@section('title', 'Gallery')

@section('content')

{{-- HERO --}}
<header class="nx-hero">
    <div class="container py-5 text-center">
        <div class="mx-auto" style="max-width: 640px;">
            <p class="nx-eyebrow mb-3">Gallery</p>
            <h1 class="mb-3">A closer look at Perfect Nails</h1>
            <p class="nx-text-secondary mb-0" style="font-size: 16.5px;">
                A glimpse of the treatments, care, and calm that make up every visit.
            </p>
        </div>
    </div>
</header>

<section class="nx-section" style="background: var(--nx-bg-page);">
    <div class="container">
        @if(empty($categories))
            <p class="text-center nx-text-secondary">Our gallery is being put together — check back soon.</p>
        @else
            <ul class="nav nav-pills justify-content-center mb-4 gap-2 flex-wrap" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link nx-pill active" data-bs-toggle="pill" data-bs-target="#gal-all" type="button" role="tab">
                        All
                    </button>
                </li>
                @foreach($categories as $category)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link nx-pill" data-bs-toggle="pill" data-bs-target="#gal-{{ $loop->index }}" type="button" role="tab">
                            {{ $category['name'] }}
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content">
                {{-- ALL --}}
                <div class="tab-pane fade show active" id="gal-all" role="tabpanel">
                    <div class="nx-gallery-grid">
                        @foreach($categories as $category)
                            @foreach($category['images'] as $image)
                                <button type="button" class="nx-gallery-item" data-bs-toggle="modal" data-bs-target="#galleryLightbox"
                                        data-image="{{ asset($image['file']) }}" data-caption="{{ $image['alt'] }}">
                                    <img src="{{ asset($image['file']) }}" alt="{{ $image['alt'] }}" loading="lazy">
                                </button>
                            @endforeach
                        @endforeach
                    </div>
                </div>

                {{-- PER CATEGORY --}}
                @foreach($categories as $category)
                    <div class="tab-pane fade" id="gal-{{ $loop->index }}" role="tabpanel">
                        <div class="nx-gallery-grid">
                            @foreach($category['images'] as $image)
                                <button type="button" class="nx-gallery-item" data-bs-toggle="modal" data-bs-target="#galleryLightbox"
                                        data-image="{{ asset($image['file']) }}" data-caption="{{ $image['alt'] }}">
                                    <img src="{{ asset($image['file']) }}" alt="{{ $image['alt'] }}" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

{{-- LIGHTBOX (single shared modal, image swapped via the tiny script below) --}}
<div class="modal fade" id="galleryLightbox" tabindex="-1" aria-hidden="true" aria-labelledby="galleryLightboxLabel">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 nx-body" style="border-radius: var(--nx-radius-lg); overflow: hidden;">
            <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" style="z-index: 2;" data-bs-dismiss="modal" aria-label="Close"></button>
            <img id="galleryLightboxImage" src="" alt="" class="w-100" style="max-height: 80vh; object-fit: contain; background: var(--nx-brand-dark);">
            <p id="galleryLightboxLabel" class="text-center small nx-text-secondary py-3 mb-0"></p>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        var modal = document.getElementById('galleryLightbox');
        if (!modal) return;
        modal.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;
            if (!trigger) return;
            var img = document.getElementById('galleryLightboxImage');
            var caption = document.getElementById('galleryLightboxLabel');
            img.src = trigger.getAttribute('data-image');
            img.alt = trigger.getAttribute('data-caption') || '';
            caption.textContent = trigger.getAttribute('data-caption') || '';
        });
    })();
</script>
@endpush
@endsection
