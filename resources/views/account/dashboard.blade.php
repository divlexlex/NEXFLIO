{{-- Client Website Dashboard — the logged-in Client's personalized home
     (Perfect Nails logo and the navbar's Dashboard link both land here; see
     partials/nav). Behind ['auth', 'role:4'] — see routes/web.php. All data
     below is real: $upcoming / $recentHistory / $pendingCount / calendar /
     notifications / suggestions all come from ClientAccountController@dashboard
     querying the signed-in client's own records. No appointment, count,
      history, or suggestion is ever hardcoded.

      Notifications live in the navbar bell (see partials/nav); the monthly
      appointment calendar lives in a pop-up modal opened from the upcoming
      card's "View calendar" button (or auto-opened after month navigation). --}}
@extends('layouts.public')

@section('title', 'My Account')

@section('content')
<header class="nx-hero">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <p class="nx-eyebrow mb-2">My Account</p>
                <h1 class="mb-2">Welcome back, {{ explode(' ', auth()->user()->name)[0] }}</h1>
                <p class="nx-text-secondary mb-0">
                    @if($pendingCount > 0)
                        You have {{ $pendingCount }} {{ \Illuminate\Support\Str::plural('booking', $pendingCount) }} awaiting verification.
                    @elseif($upcoming)
                        Your next visit is coming up — details below.
                    @else
                        Here's what's happening with your Perfect Nails visits.
                    @endif
                </p>
            </div>
            <a href="{{ route('account.booking.start') }}" class="nx-btn nx-btn-primary"
               data-bs-toggle="modal" data-bs-target="#bookServiceModal">
                <i class="bi bi-calendar-plus me-1"></i>Book an Appointment
            </a>
        </div>
    </div>
</header>

<section class="nx-section" style="background: var(--nx-bg-page); padding-top: 48px;">
    <div class="container">
        {{-- SPECIAL FOR YOU — same live Promos as the Guest Home's Special
             Offers section (see ClientAccountController@dashboard), so a
             signed-in Client sees the identical current offers, just from
             their own home. --}}
        @if($promos->isNotEmpty())
        <div class="mb-5">
            <div class="nx-section-header mb-3">
                <div>
                    <p class="nx-eyebrow mb-1">Limited Time</p>
                    <h2 class="h4 mb-0">Special for You</h2>
                </div>
                <a href="{{ route('offers') }}" class="nx-section-link">See all offers &rarr;</a>
            </div>
            @include('partials.offers-section', ['promos' => $promos, 'limit' => 4])
        </div>
        @endif

        <div class="row g-4 mb-5">
            {{-- UPCOMING APPOINTMENT --}}
            <div class="col-12 col-lg-6">
                <p class="nx-eyebrow mb-2">Upcoming Appointment</p>
                @if($upcoming)
                    <div class="nx-upcoming-card">
                        @if($upcoming->service?->image_url)
                            <div class="nx-upcoming-card-media">
                                <img src="{{ $upcoming->service->image_url }}" alt="{{ $upcoming->service->name }}">
                            </div>
                        @endif
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <h3 class="h5 mb-0">{{ $upcoming->service->name ?? 'Service' }}</h3>
                                <span class="nx-status-badge nx-status-upcoming">{{ $upcoming->status->label() }}</span>
                            </div>
                            <p class="nx-text-secondary mb-1">
                                <i class="bi bi-calendar3 me-2"></i>{{ $upcoming->appointment_date->format('F j, Y') }}
                                &middot; {{ \Illuminate\Support\Carbon::parse($upcoming->start_time)->format('g:i A') }}
                            </p>
                            @if($upcoming->personnel)
                                <p class="nx-text-secondary mb-0"><i class="bi bi-person me-2"></i>{{ $upcoming->personnel->name }}</p>
                            @endif
                            <div class="mt-3">
                                <button type="button" class="nx-btn nx-btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#calendarModal">
                                    <i class="bi bi-calendar3 me-1"></i>View calendar
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="nx-card p-4 text-center">
                        <p class="nx-text-secondary mb-3">No upcoming appointments.</p>
                        <button type="button" class="nx-btn nx-btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#calendarModal">
                            <i class="bi bi-calendar3 me-1"></i>View calendar
                        </button>
                    </div>
                @endif
            </div>

            {{-- RECENT SERVICE HISTORY --}}
            <div class="col-12 col-lg-6">
                <p class="nx-eyebrow mb-2">Recent Service History</p>
                @if($recentHistory->isNotEmpty())
                    <div class="d-flex flex-column gap-2">
                        @foreach($recentHistory as $appointment)
                            <div class="nx-booking-row d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="fw-semibold mb-0">{{ $appointment->service->name ?? 'Service' }}</p>
                                    <p class="small nx-text-secondary mb-0">{{ $appointment->appointment_date->format('M j, Y') }}</p>
                                </div>
                                <span class="nx-status-badge nx-status-done">{{ $appointment->status->label() }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="nx-card p-4 text-center">
                        <p class="nx-text-secondary mb-0">No completed visits yet.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- SUGGESTED FOR YOU --}}
        @if($suggestions->isNotEmpty())
            <p class="nx-eyebrow mb-2">Suggested for You</p>
            <p class="nx-text-secondary mb-3" style="margin-top: -8px;">Services you haven't tried yet.</p>
            <div class="row g-3">
                @foreach($suggestions as $suggestion)
                    <div class="col-6 col-md-3">
                        @include('partials.item-card', [
                            'url' => route('catalog.service', $suggestion['id']),
                            'imageUrl' => $suggestion['image_url'],
                            'title' => $suggestion['title'],
                            'price' => $suggestion['price'],
                            'subtitle' => $suggestion['subtitle'],
                            'badge' => 'Service',
                            'ctaText' => 'Book',
                        ])
                    </div>
                @endforeach
            </div>
        @endif

        {{-- CALENDAR MODAL — the monthly grid + per-day rows live here now;
             opened from the "View calendar" button or auto-opened after a
             month navigation (which reloads with ?calendar=1). --}}
        <div class="modal fade" id="calendarModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content nx-body" style="border-radius: var(--nx-radius-lg); overflow: hidden;">
                    <div class="modal-header" style="border-color: var(--nx-border);">
                        <p class="nx-eyebrow mb-0">Your Appointments Calendar</p>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <a href="{{ route('account.dashboard', ['month' => $prevMonth, 'calendar' => 1]) }}"
                               class="btn btn-sm btn-outline-secondary" aria-label="Previous month">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                            <h3 class="h6 mb-0">{{ $calendarMonth->format('F Y') }}</h3>
                            <a href="{{ route('account.dashboard', ['month' => $nextMonth, 'calendar' => 1]) }}"
                               class="btn btn-sm btn-outline-secondary" aria-label="Next month">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>
                        <div class="nx-calendar mb-1">
                            @foreach(['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $weekday)
                                <div class="nx-calendar-weekday">{{ $weekday }}</div>
                            @endforeach
                            @foreach($calendarCells as $cell)
                                @if($cell === null)
                                    <div class="nx-calendar-cell nx-calendar-cell-empty"></div>
                                @elseif($cell['hasAppointment'])
                                    <a href="#day-{{ $cell['date'] }}"
                                       class="nx-calendar-cell has-appt {{ $cell['isToday'] ? 'is-today' : '' }}">
                                        {{ $cell['day'] }}
                                        <span class="nx-calendar-dot"></span>
                                    </a>
                                @else
                                    <span class="nx-calendar-cell {{ $cell['isToday'] ? 'is-today' : '' }}">{{ $cell['day'] }}</span>
                                @endif
                            @endforeach
                        </div>

                        <hr class="my-3" style="border-color: var(--nx-border);">

                        @if($appointmentsByDate->isEmpty())
                            <p class="small nx-text-secondary mb-0">No appointments in {{ $calendarMonth->format('F') }}.</p>
                        @else
                            <div class="d-flex flex-column gap-2">
                                @foreach($appointmentsByDate as $date => $dayAppointments)
                                    <div id="day-{{ $date }}" class="nx-booking-row">
                                        <p class="small fw-semibold mb-1">{{ \Illuminate\Support\Carbon::parse($date)->format('F j, Y') }}</p>
                                        @foreach($dayAppointments as $appointment)
                                            @php($statusClass = match($appointment->status->value) {
                                                'unverified' => 'nx-status-pending',
                                                'booked', 'in-service' => 'nx-status-upcoming',
                                                'cancelled', 'no-show' => 'nx-status-cancelled',
                                                default => 'nx-status-done',
                                            })
                                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 {{ !$loop->last ? 'mb-1' : '' }}">
                                                <span class="small nx-text-secondary">
                                                    {{ $appointment->service->name ?? 'Service' }}
                                                    &middot; {{ \Illuminate\Support\Carbon::parse($appointment->start_time)->format('g:i A') }}
                                                </span>
                                                <span class="nx-status-badge {{ $statusClass }}">{{ $appointment->status->label() }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <script>
            (function () {
                if (!new URLSearchParams(window.location.search).has('calendar')) return;
                var el = document.getElementById('calendarModal');
                if (el) bootstrap.Modal.getOrCreateInstance(el).show();
            })();
        </script>
    </div>
</section>
@endsection
