@extends('layouts.public')

@section('title', $title)

@section('content')
<section class="py-4 py-lg-5">
    <div class="container">
        <a href="{{ route('landing') }}" class="btn btn-sm btn-outline-spa mb-4">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>

        <div class="row g-4 align-items-center">
            {{-- IMAGE --}}
            <div class="col-lg-6">
                @if(!empty($imageUrl))
                    <img src="{{ $imageUrl }}" alt="{{ $title }}" class="img-fluid rounded-4 w-100"
                         style="max-height: 460px; object-fit: cover;">
                @else
                    <div class="rounded-4 d-flex align-items-center justify-content-center bg-ombre text-white w-100"
                         style="height: 340px;">
                        <i class="bi bi-image display-3 opacity-50"></i>
                    </div>
                @endif
            </div>

            {{-- DETAILS --}}
            <div class="col-lg-6">
                <span class="badge mb-2" style="background: var(--spa-blush); color: var(--spa-espresso);">
                    {{ ucfirst($category ?? $type) }}
                </span>
                <h1 class="mb-2">{{ $title }}</h1>
                <p class="display-6 text-gold mb-3">₱{{ number_format($price, 2) }}</p>

                @if(!empty($meta))
                    <p class="text-muted mb-3"><i class="bi bi-info-circle me-1"></i>{{ $meta }}</p>
                @endif

                @if(!empty($description))
                    <p class="mb-4" style="max-width: 520px;">{{ $description }}</p>
                @endif

                <button type="button" class="btn btn-gold btn-lg px-4" data-bs-toggle="modal" data-bs-target="#getAppModal">
                    <i class="bi bi-phone me-2"></i>{{ $cta }}
                </button>
            </div>
        </div>
    </div>
</section>
@endsection
