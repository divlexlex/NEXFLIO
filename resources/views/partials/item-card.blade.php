{{-- Image catalog card linking to a detail page.
     Expects: $url, $title, $price; optional: $imageUrl, $subtitle, $badge, $ctaText --}}
<a href="{{ $url }}" class="text-decoration-none d-block h-100">
    <div class="card-spa h-100 overflow-hidden">
        <div class="ratio ratio-4x3">
            @if(!empty($imageUrl))
                <img src="{{ $imageUrl }}" alt="{{ $title }}" style="object-fit: cover;">
            @else
                <div class="bg-ombre d-flex align-items-center justify-content-center text-white">
                    <i class="bi bi-image fs-1 opacity-50"></i>
                </div>
            @endif
        </div>
        <div class="p-3">
            @if(!empty($badge))
                <span class="badge mb-1" style="background: var(--spa-blush); color: var(--spa-espresso);">{{ $badge }}</span>
            @endif
            <div class="d-flex justify-content-between align-items-start gap-2">
                <h3 class="h6 mb-1">{{ $title }}</h3>
                <span class="text-gold fw-bold text-nowrap">₱{{ number_format($price, 2) }}</span>
            </div>
            @if(!empty($subtitle))
                <p class="small text-muted mb-2">{{ $subtitle }}</p>
            @endif
            <small class="text-gold fw-semibold">{{ $ctaText ?? 'View details' }} <i class="bi bi-arrow-right"></i></small>
        </div>
    </div>
</a>
