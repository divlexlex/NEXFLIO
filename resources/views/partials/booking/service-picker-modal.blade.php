{{-- Floating "Book an Appointment" entry point — the authenticated Client's
     entry into the booking wizard when they click "Book an Appointment"
     directly (nav, dashboard, CTAs) rather than arriving with an
     already-known service from Services/a catalog detail page.

     Opens on a Branch Booking vs. Home Service choice (mirrors
     account/booking/start.blade.php's two options), matching this being the
     new one-floating-box entry point for the whole flow:
       - Branch Booking: real, working — category pills + service grid
         (same pattern as partials/services-section.blade.php), picking a
         service navigates straight into the real Step 2 (Date & Time) page.
       - Home Service: intentionally stubbed to "Coming Soon" for now — the
         Phase 3B backend/routes still exist and work (see
         BookingController's home.* methods, tests/Feature/HomeServiceBookingTest),
         just not surfaced through this entry point yet. Not removed, just
         not linked here.

     Included once, site-wide, from layouts.public (gated to authenticated
     Clients only — see there) so every "Book an Appointment" trigger across
     the Website can just point at this one modal (#bookServiceModal) via
     data-bs-toggle="modal" instead of each page building its own. Bootstrap
     still treats the trigger's real href as a fallback: if the modal JS
     never loads, the link still lands on the real, full booking-type-choice
     page (account.booking.start) — nothing here is a JS-only dead end.

     Queries here directly rather than via a passed-in variable, since this
     partial is included from the shared layout, not from a specific
     controller action — same real, active, branch-bookable Service records
     BookingController@serviceIndex uses (App\Models\Service::bookableAtBranch()). --}}
@php
    $modalServicesByCategory = \App\Models\Service::bookableAtBranch()
        ->orderBy('name')
        ->get()
        ->groupBy('category');
@endphp
<div class="modal fade" id="bookServiceModal" tabindex="-1" aria-hidden="true" aria-labelledby="bookServiceModalLabel">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 nx-body" style="border-radius: var(--nx-radius-lg); overflow: hidden;">
            <div class="modal-header" style="border-color: var(--nx-border);">
                <div>
                    <p class="nx-eyebrow mb-1">Book an Appointment</p>
                    <h2 id="bookServiceModalLabel" class="h5 mb-0">How would you like to be served?</h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" style="max-height: 75vh; overflow-y: auto;">
                <ul class="nav nav-pills justify-content-center mb-4 gap-2" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link nx-pill active" data-bs-toggle="pill" data-bs-target="#bookType-branch" type="button" role="tab">
                            <i class="bi bi-shop me-1"></i>Branch Booking
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link nx-pill" data-bs-toggle="pill" data-bs-target="#bookType-home" type="button" role="tab">
                            <i class="bi bi-house-heart me-1"></i>Home Service
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    {{-- BRANCH BOOKING — real, working flow --}}
                    <div class="tab-pane fade show active" id="bookType-branch" role="tabpanel">
                        @if($modalServicesByCategory->isEmpty())
                            <p class="text-center nx-text-secondary my-4 mb-0">No bookable services are available right now. Please check back soon.</p>
                        @else
                            <ul class="nav nav-pills justify-content-center mb-4 gap-2 flex-wrap" role="tablist">
                                @foreach($modalServicesByCategory as $category => $services)
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link nx-pill {{ $loop->first ? 'active' : '' }}"
                                                data-bs-toggle="pill" data-bs-target="#modal-cat-{{ $loop->index }}"
                                                type="button" role="tab">
                                            {{ $category }}
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="tab-content">
                                @foreach($modalServicesByCategory as $category => $services)
                                    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="modal-cat-{{ $loop->index }}" role="tabpanel">
                                        <div class="row g-3">
                                            @foreach($services as $service)
                                                <div class="col-6 col-md-4">
                                                    <a href="{{ route('account.booking.branch.service.show', $service->id) }}" class="nx-card d-block h-100">
                                                        <div class="nx-card-media" style="height: 110px;">
                                                            @if($service->image_url)
                                                                <img src="{{ $service->image_url }}" alt="{{ $service->name }}">
                                                            @else
                                                                <i class="bi bi-image fs-3 opacity-50"></i>
                                                            @endif
                                                        </div>
                                                        <div class="nx-card-body" style="padding: 14px 16px 16px;">
                                                            <h3 class="nx-card-title h6 mb-1" style="font-size: 15px;">{{ $service->name }}</h3>
                                                            <p class="small nx-text-secondary mb-2">{{ $service->duration_minutes }} mins</p>
                                                            <span class="nx-card-price">₱{{ number_format($service->price, 2) }}</span>
                                                        </div>
                                                    </a>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- HOME SERVICE — stubbed for now; real Phase 3B flow
                         still exists at account.booking.home.* routes, just
                         not linked from this entry point yet. --}}
                    <div class="tab-pane fade" id="bookType-home" role="tabpanel">
                        <div class="text-center py-5">
                            <div class="nx-value-icon mx-auto mb-3" style="width: 56px; height: 56px; font-size: 24px;">
                                <i class="bi bi-house-heart"></i>
                            </div>
                            <h3 class="h5 mb-2">Coming Soon</h3>
                            <p class="nx-text-secondary mb-0" style="max-width: 380px; margin-inline: auto;">
                                Home Service booking through the Website is on its way. For now, please book
                                a Home Service visit through the Perfect Nails app, or visit our branch.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
