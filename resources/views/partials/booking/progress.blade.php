{{-- Booking wizard progress indicator (Branch: Phase 3A, Home Service:
     Phase 3B — Home Service inserts an extra "Address" step after Service).
     Expects: $step, matching the order in BookingController; optional
     $steps to override the default Branch label set. --}}
@php
    $steps = $steps ?? ['Service', 'Date & Time', 'Details', 'Review', 'Payment', 'Submitted'];
@endphp
<div class="nx-booking-stepper">
    @foreach($steps as $i => $label)
        @php($n = $i + 1)
        <div class="nx-booking-step {{ $n < $step ? 'is-done' : '' }} {{ $n === $step ? 'is-active' : '' }}">
            <span class="nx-booking-step-index">
                @if($n < $step)
                    <i class="bi bi-check-lg"></i>
                @else
                    {{ $n }}
                @endif
            </span>
            <span class="nx-booking-step-label">{{ $label }}</span>
        </div>
        @if(!$loop->last)
            <span class="nx-booking-step-connector"></span>
        @endif
    @endforeach
</div>
