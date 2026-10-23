{{-- Home Service Booking — Step 3: Date, Personnel & Time (Phase 3B).
     Reuses the exact same availability logic as Branch Booking
     (BookingController@eligiblePersonnel / buildSlotGrid) — no separate
     Home Service staff pool or travel/buffer time exists in the schema, so
     none is fabricated here (see the completion report's Known Gaps). --}}
@extends('layouts.public')

@php
    $homeSteps = ['Service', 'Address', 'Date & Time', 'Details', 'Review', 'Payment', 'Submitted'];
@endphp

@section('title', 'Book Home Service — Date & Time')

@section('content')
<section class="nx-booking-floating-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

                <div class="nx-booking-panel mb-4">
                    <div class="nx-booking-panel-header">
                        <div>
                            <p class="nx-eyebrow mb-1">HOME SERVICE</p>
                            <h1 class="h5 mb-0">Choose Date &amp; Time</h1>
                        </div>
                        @include('partials.booking.progress-compact', ['step' => 3, 'steps' => $homeSteps])
                    </div>

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

                    @foreach($extras ?? [] as $extra)
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="nx-card-media" style="width: 64px; height: 64px; border-radius: var(--nx-radius-md); flex-shrink: 0;">
                            @if($extra['service']->image_url)
                                <img src="{{ $extra['service']->image_url }}" alt="{{ $extra['service']->name }}">
                            @else
                                <i class="bi bi-image opacity-50"></i>
                            @endif
                        </div>
                        <div>
                            <p class="fw-semibold mb-0">{{ $extra['service']->name }}@if(! empty($extra['variant_label'])) <span class="nx-text-secondary">— {{ $extra['variant_label'] }}</span>@endif</p>
                            <p class="small nx-text-secondary mb-0">{{ $extra['service']->duration_minutes }} mins &middot; ₱{{ number_format($extra['price'] ?? $extra['service']->price, 2) }}</p>
                        </div>
                    </div>
                    @endforeach

                    {{-- DATE --}}
                    <form method="GET" action="{{ route('account.booking.home.schedule') }}" id="dateForm">
                        <input type="hidden" name="service" value="{{ $service->id }}">
                        @include('partials.booking.carry-extras', ['extras' => $extras])
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
                        @include('partials.booking.carry-extras', ['extras' => $extras])
                        @include('partials.booking.carry-fields', ['address' => $address])
                        @include('partials.booking.personnel-picker')
                    </form>

                    <hr class="my-4" style="border-color: var(--nx-border);">

                    {{-- TIME --}}
                    @php
                        $slotQuery = array_merge([
                            'service' => $service->id,
                            'date' => $date,
                            'time' => null,
                        ], $address);
                        if ($isMultiStaff) {
                            foreach ($staffSelection as $positionId => $value) {
                                $slotQuery['staff'][$positionId] = $value;
                            }
                        } else {
                            $slotQuery['personnel'] = $personnelParam;
                        }
                        if (! empty($extras)) {
                            $slotQuery['extras'] = collect($extras)->map(fn ($extra) => array_filter([
                                'service_id' => $extra['service']->id,
                                'variant_label' => $extra['variant_label'],
                                'personnel_id' => $extra['personnel_id'],
                            ], fn ($value) => $value !== null && $value !== ''))->all();
                        }
                    @endphp
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
                                       href="{{ route('account.booking.home.details', array_merge($slotQuery, ['time' => $slot['time']])) }}">
                                        {{ \Illuminate\Support\Carbon::parse($slot['time'])->format('g:i A') }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                <a href="{{ route('account.booking.home.address', array_merge(['service' => $service->id, 'extras' => ! empty($extras) ? collect($extras)->map(fn ($extra) => array_filter(['service_id' => $extra['service']->id, 'variant_label' => $extra['variant_label'], 'personnel_id' => $extra['personnel_id']], fn ($value) => $value !== null && $value !== ''))->all() : null], $address)) }}" class="nx-btn nx-btn-outline">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
