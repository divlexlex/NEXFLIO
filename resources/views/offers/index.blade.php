@extends('layouts.public')

@section('title', 'Offers')

@section('content')
<div class="container py-5">
    <div class="text-center mb-4">
        <p class="nx-eyebrow mb-2">Limited Time</p>
        <h2 class="nx-section-title">Special Offers</h2>
        <p class="nx-text-secondary mt-2 mb-0">Current promos and bundles — tap any offer for details.</p>
    </div>

    @if($promos->isEmpty())
        <p class="text-center nx-text-secondary">No active offers right now — check back soon.</p>
    @else
        @include('partials.offers-section', ['promos' => $promos])
    @endif
</div>
@endsection
