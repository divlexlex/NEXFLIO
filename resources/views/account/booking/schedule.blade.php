{{-- Branch Booking — Step 3: Date, Personnel & Time (Phase 3A).
     Availability is computed server-side in BookingController@schedule /
     buildSlotGrid: eligible personnel mirrors the existing bookable-staff
     rule (active staff profile, not on break, no approved leave covering
     the date — same as API\AppointmentController@personnel), and each time
     slot is disabled when the selected personnel (or, for "No preference",
     every eligible personnel) already has a non-cancelled appointment at
     that exact date+time — the same collision rule the booking submission
     itself enforces. This is a display aid only; store() re-checks
     everything from scratch. --}}
@extends('layouts.public')

@section('title', 'Book — Date & Time')

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
                            <p class="nx-eyebrow mb-1">Branch Booking</p>
                            <h1 class="h5 mb-0">Choose Date &amp; Time</h1>
                        </div>
                        @include('partials.booking.progress-compact', ['step' => 2])
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
                            <p class="fw-semibold mb-0">{{ $service->name }}@if($variant) <span class="nx-text-secondary">— {{ $variant->label }}</span>@endif</p>
                            <p class="small nx-text-secondary mb-0">{{ $service->duration_minutes }} mins &middot; ₱{{ number_format($variant?->price ?? $service->price, 2) }}</p>
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
                    <form method="GET" action="{{ route('account.booking.branch.schedule') }}" id="dateForm">
                        <input type="hidden" name="service" value="{{ $service->id }}">
                        @if($variant)<input type="hidden" name="variant" value="{{ $variant->id }}">@endif
                        @include('partials.booking.carry-extras', ['extras' => $extras])
                        <label class="form-label nx-eyebrow">Date</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="date" name="date" value="{{ $date }}" min="{{ $minDate }}" max="{{ $maxDate }}"
                                   class="form-control" style="max-width: 220px;" onchange="document.getElementById('dateForm').submit()" required>
                            <button type="submit" class="nx-btn nx-btn-outline">Update</button>
                        </div>
                    </form>

                    <hr class="my-4" style="border-color: var(--nx-border);">

                    {{-- PERSONNEL --}}
                    <form method="GET" action="{{ route('account.booking.branch.schedule') }}" id="personnelForm">
                        <input type="hidden" name="service" value="{{ $service->id }}">
                        @if($variant)<input type="hidden" name="variant" value="{{ $variant->id }}">@endif
                        <input type="hidden" name="date" value="{{ $date }}">
                        @include('partials.booking.carry-extras', ['extras' => $extras])
                        @include('partials.booking.personnel-picker')
                    </form>

                    <hr class="my-4" style="border-color: var(--nx-border);">

                    {{-- TIME --}}
                    @php
                        $slotQuery = array_filter([
                            'service' => $service->id,
                            'variant' => $variant?->id,
                            'date' => $date,
                            'personnel' => $isMultiStaff ? null : $personnelParam,
                            'time' => null,
                        ]);
                        if ($isMultiStaff) {
                            foreach ($staffSelection as $positionId => $value) {
                                $slotQuery['staff'][$positionId] = $value;
                            }
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
                                       href="{{ route('account.booking.branch.details', array_filter(array_merge($slotQuery, ['time' => $slot['time']]))) }}">
                                        {{ \Illuminate\Support\Carbon::parse($slot['time'])->format('g:i A') }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                <a href="{{ route('account.booking.branch.service.show', $service->id) }}" class="nx-btn nx-btn-outline">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
