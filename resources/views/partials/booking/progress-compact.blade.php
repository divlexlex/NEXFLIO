{{-- Compact step indicator for the floating-card booking pages (steps 2-6
     after the "Book a Service" modal) — small dots instead of the full
     circle+label+connector stepper (partials/booking/progress.blade.php),
     which was sized for a full-width page, not a narrow floating card.
     Expects: $step; optional $steps to override the default Branch label set. --}}
@php
    $steps = $steps ?? ['Service', 'Date & Time', 'Details', 'Review', 'Payment', 'Submitted'];
@endphp
<div class="text-md-end">
    <div class="nx-booking-dots justify-content-md-end mb-1">
        @foreach($steps as $i => $label)
            @php($n = $i + 1)
            <span class="nx-booking-dot {{ $n < $step ? 'is-done' : '' }} {{ $n === $step ? 'is-active' : '' }}"
                  title="{{ $label }}"></span>
        @endforeach
    </div>
    <p class="small nx-text-secondary mb-0">Step {{ $step }} of {{ count($steps) }}</p>
</div>
