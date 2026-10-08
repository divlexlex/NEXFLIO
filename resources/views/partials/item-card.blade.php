{{-- Image catalog card. Pass $url for real DB-backed items (links to their detail
     page) — omit it for items without a detail page.

     For a no-$url item, the card must still never fabricate a booking: a Guest
     gets the existing "Get the App" modal, but an authenticated Client must NOT
     see that Guest modal (see the Phase 3B modal-regression fix) — instead the
     card sends them to the real booking start chooser (account.booking.start),
     which only ever lists real, DB-backed, active services. This is the one
     spot in the whole Website where auth state has to be checked *inline*
     rather than relying on a destination page's own @auth block, because a
     card without $url has no detail page to route to at all.
     Expects: $title, $price; optional: $url, $imageUrl, $subtitle, $badge, $ctaText --}}
@php
    $isLink = !empty($url);
    $isAuthedClient = auth()->check() && auth()->user()->isClient();
    $fallbackHref = $isAuthedClient ? route('account.booking.start') : null;
@endphp
<{{ $isLink || $fallbackHref ? 'a' : 'button' }}
    @if($isLink) href="{{ $url }}"
    @elseif($fallbackHref) href="{{ $fallbackHref }}" data-bs-toggle="modal" data-bs-target="#bookServiceModal"
    @else type="button" data-bs-toggle="modal" data-bs-target="#getAppModal" @endif
    class="nx-card d-block h-100 w-100 text-start border-0 bg-transparent p-0"
    style="text-decoration:none; color:inherit; cursor:pointer;"
>
    <div class="nx-card-media" style="height: 180px;">
        @if(!empty($imageUrl))
            <img src="{{ $imageUrl }}" alt="{{ $title }}">
        @else
            <i class="bi bi-image fs-1 opacity-50"></i>
        @endif
    </div>
    <div class="nx-card-body">
        @if(!empty($badge))
            <span class="nx-badge mb-2">{{ $badge }}</span>
        @endif
        <div class="d-flex justify-content-between align-items-start gap-2">
            <h3 class="nx-card-title h6 mb-1">{{ $title }}</h3>
        </div>
        @if(!empty($subtitle))
            <p class="small nx-text-secondary mb-2">{{ $subtitle }}</p>
        @endif
        <div class="d-flex justify-content-between align-items-center">
            <span class="nx-card-price">₱{{ number_format($price, 2) }}</span>
            <small class="nx-text-accent fw-semibold">{{ $ctaText ?? 'View details' }} <i class="bi bi-arrow-right"></i></small>
        </div>
    </div>
</{{ $isLink || $fallbackHref ? 'a' : 'button' }}>
