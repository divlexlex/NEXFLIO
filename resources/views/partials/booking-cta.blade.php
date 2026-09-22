{{-- Shared booking entry point: authenticated Clients go into the real
     Branch Booking flow (Phase 3A — account.booking.start), guests see the
     Website/App choice modal defined once in layouts.public (#getAppModal).
     Reused across Website pages so there is one coherent booking entry
     experience, not a per-page modal.
     Expects: $label; optional: $class (extra classes), $outlineClass for the
     unauthenticated variant styling override. --}}
@auth
    <a href="{{ route('account.booking.start') }}" class="nx-btn nx-btn-primary {{ $class ?? '' }}"
       data-bs-toggle="modal" data-bs-target="#bookServiceModal">{{ $label }}</a>
@else
    <button type="button" class="nx-btn nx-btn-primary {{ $class ?? '' }}" data-bs-toggle="modal" data-bs-target="#getAppModal">
        {{ $label }}
    </button>
@endauth
