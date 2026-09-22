{{-- Home Service Booking — Step 3: Date, Personnel & Time (Phase 3B).
     Reuses the exact same availability logic as Branch Booking
     (BookingController@eligiblePersonnel / buildSlotGrid) — no separate
     Home Service staff pool or travel/buffer time exists in the schema, so
     none is fabricated here (see the completion report's Known Gaps). --}}
@extends('layouts.public')

@php($homeSteps = ['Service', 'Address', 'Date & Time', 'Details', 'Review', 'Payment', 'Submitted'])

@section('title', 'Book Home Service — Date & Time')

@section('content')
<header class="nx-hero">
    <div class="container py-5">
        <p class="nx-eyebrow mb-2">Home Service</p>
        <h1 class="mb-0">Choose Date &amp; Time</h1>
    </div>
</header>

<section class="nx-section" style="background: var(--nx-bg-page); padding-top: 40px;">
    <div class="container">
        @include('partials.booking.progress', ['step' => 3, 'steps' => $homeSteps])

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="nx-booking-panel mb-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="nx-card-media" style="width: 64px; height: 64px; border-radius: var(--nx-radius-md); flex-shrink: 0;">
                            @if($service->image_url)
                                <img src="{{ $service->image_url }}" alt="{{ $service->name }}">
                            @else
                                <i class="bi bi-image opacity-50"></i>
                            @endif
                        </div>
                        <div>
                            <p class="fw-semibold mb-0">{{ $service->name }}</p>
                            <p class="small nx-text-secondary mb-0">{{ $service->duration_minutes }} mins &middot; ₱{{ number_format($service->price, 2) }}</p>
                        </div>
                    </div>

                    {{-- DATE --}}
                    <form method="GET" action="{{ route('account.booking.home.schedule') }}" id="dateForm">
                        <input type="hidden" name="service" value="{{ $service->id }}">
                        @include('partials.booking.carry-fields', ['address' => $address])
                        <label class="form-label nx-eyebrow">Date</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="date" name="date" value="{{ $date }}" min="{{ $minDate }}" max="{{ $maxDate }}"
                                   class="form-control" style="max-width: 220px;" onchange="document.getElementById('dateForm').submit()" required>
                            <button type="submit" class="nx-btn nx-btn-outline">Update</button>
                        </div>
                    </form>

                    <hr class="my-4" style="border-color: var(--nx-border);">

                    {{-- PERSONNEL --}}
                    <form method="GET" action="{{ route('account.booking.home.schedule') }}" id="personnelForm">
                        <input type="hidden" name="service" value="{{ $service->id }}">
                        <input type="hidden" name="date" value="{{ $date }}">
                        @include('partials.booking.carry-fields', ['address' => $address])
                        <label class="form-label nx-eyebrow">Preferred Spa Personnel</label>

                        @if($eligiblePersonnel->isEmpty())
                            <div class="alert alert-warning mb-0">No Spa Personnel are available on this date. Please choose a different date.</div>
                        @else
                            <div class="row g-2">
                                <div class="col-sm-6 col-lg-4">
                                    <label class="nx-select-card d-flex align-items-center gap-2 {{ $personnelParam === 'any' ? 'is-selected' : '' }}">
                                        <input type="radio" name="personnel" value="any" {{ $personnelParam === 'any' ? 'checked' : '' }} onchange="document.getElementById('personnelForm').submit()">
                                        <span>No preference<br><small class="nx-text-secondary">Any available personnel</small></span>
                                    </label>
                                </div>
                                @foreach($eligiblePersonnel as $person)
                                    <div class="col-sm-6 col-lg-4">
                                        <label class="nx-select-card d-flex align-items-center gap-2 {{ (string) $personnelParam === (string) $person->id ? 'is-selected' : '' }}">
                                            <input type="radio" name="personnel" value="{{ $person->id }}" {{ (string) $personnelParam === (string) $person->id ? 'checked' : '' }} onchange="document.getElementById('personnelForm').submit()">
                                            <span>{{ $person->name }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </form>

                    <hr class="my-4" style="border-color: var(--nx-border);">

                    {{-- TIME --}}
                    <label class="form-label nx-eyebrow">Time</label>
                    @if(!$personnelParam)
                        <p class="nx-text-secondary small mb-0">Choose Spa Personnel above to see available times.</p>
                    @elseif(empty($slots))
                        <p class="nx-text-secondary small mb-0">No time slots configured for this service.</p>
                    @elseif(collect($slots)->where('available', true)->isEmpty())
                        <div class="alert alert-warning mb-0">No open slots left for this date. Please try another date or personnel.</div>
                    @else
                        <div class="nx-slot-grid">
                            @foreach($slots as $slot)
                                @if($slot['available'])
                                    <a class="nx-slot-btn {{ $selectedTime === $slot['time'] ? 'is-selected' : '' }}"
                                       href="{{ route('account.booking.home.details', array_merge(['service' => $service->id, 'date' => $date, 'personnel' => $personnelParam, 'time' => $slot['time']], $address)) }}">
                                        {{ \Illuminate\Support\Carbon::parse($slot['time'])->format('g:i A') }}
                                    </a>
                                @else
                                    <span class="nx-slot-btn is-disabled">{{ \Illuminate\Support\Carbon::parse($slot['time'])->format('g:i A') }}</span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                <a href="{{ route('account.booking.home.address', array_merge(['service' => $service->id], $address)) }}" class="nx-btn nx-btn-outline">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
