{{-- Promo card — used for the Home teaser (landing/index.blade.php), the
     Client Dashboard's "Special for You", and the full /offers page.
     Expects: $promo (with `services` eager-loaded). --}}
@php
    $discountLabel = $promo->discountLabel();
    $displayPrice = $promo->displayPrice();
    $linkedService = $promo->services->first();
@endphp
<a href="{{ route('catalog.promo', $promo->id) }}" class="nx-promo-card">
    <div class="nx-promo-card-media">
        @if($promo->image_url)
            <img src="{{ $promo->image_url }}" alt="{{ $promo->title }}">
        @else
            <div class="d-flex align-items-center justify-content-center h-100">
                <i class="bi bi-gift fs-1 opacity-50"></i>
            </div>
        @endif
        @if($discountLabel)
            <span class="nx-promo-ribbon">{{ $discountLabel }}</span>
        @endif
    </div>
    <div class="nx-promo-card-body">
        <p class="small nx-text-secondary mb-1">{{ $linkedService->name ?? 'Limited Offer' }}</p>
        <h3 class="nx-card-title h6 mb-2">{{ $promo->title }}</h3>
        @if($displayPrice !== null)
            <div class="d-flex align-items-center gap-2">
                @if($discountLabel && $linkedService)
                    <span class="nx-price-strike">₱{{ number_format($linkedService->price, 2) }}</span>
                @endif
                <span class="nx-card-price">₱{{ number_format($displayPrice, 2) }}</span>
            </div>
        @endif
    </div>
</a>
