{{-- Real, Admin-managed Promo grid — shared by the Home page teaser
     (landing/index.blade.php, limited + a "See all offers" link) and the full
     /offers page (offers/index.blade.php, unlimited). Every promo renders as
     an equal-weight card (partials.promo-card) in one row.
     Expects: $promos (with `services` eager-loaded). Optional: $limit. --}}
@php($items = isset($limit) ? $promos->take($limit) : $promos)
@if($items->isNotEmpty())
    <div class="row g-3">
        @foreach($items as $promo)
            <div class="col-6 col-md-4 col-lg-3">
                @include('partials.promo-card', ['promo' => $promo])
            </div>
        @endforeach
    </div>
@endif
