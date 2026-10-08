<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Perfect Nails Wellness and Aesthetics')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ app()->environment('local') ? asset('css/website.css') : secure_asset('css/website.css') }}" rel="stylesheet">
    <script>
        // Apply saved/system theme before paint to avoid a flash.
        (function () {
            const saved = localStorage.getItem('theme');
            const theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    @stack('head')
</head>
<body class="nx-body">
@include('partials.nav')

@yield('content')

{{-- BOOK APPOINTMENT MODAL — Guest prompt to sign in and continue booking on
     the Website. Shared by every "Book an Appointment" / "Book in the App"
     trigger site-wide, so whichever page opens it, "Continue on Website"
     returns here after login (see AuthController@destinationFor). Only shown
     to guests — authenticated Clients skip straight past this everywhere it's
     used (see landing/index.blade.php, partials/booking-cta.blade.php,
     catalog/detail.blade.php). The mobile app is not being shipped; booking
     lives entirely on the (mobile-responsive) Website.

     A trigger can optionally set data-booking-redirect="{{ url }}" (see
     catalog/detail.blade.php's service "Book" button) to send the Guest
     straight into that specific booking flow after logging in, instead of
     just back to whatever page the modal was opened from — so a Guest's
     selected service survives the login redirect intact, per the
     "preserve selected service through auth" requirement. --}}
<div class="modal fade" id="getAppModal" tabindex="-1" aria-hidden="true" aria-labelledby="getAppModalLabel">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 nx-body" style="border-radius: var(--nx-radius-lg); overflow: hidden;">
            <div class="text-center p-4" style="background: var(--nx-brand-dark); color: #fff;">
                <i class="bi bi-calendar-heart fs-1"></i>
                <h4 id="getAppModalLabel" class="nx-font-display mt-2 mb-1" style="color:#fff;">Book your appointment</h4>
                <p class="small opacity-75 mb-0">Choose how you'd like to continue.</p>
            </div>
            <div class="modal-body p-4">
                <p class="nx-eyebrow mb-2">Continue on Website</p>
                <a id="getAppModalLoginLink"
                   href="{{ route('login', ['redirect' => request()->getRequestUri()]) }}"
                   class="nx-btn nx-btn-primary w-100 justify-content-center mb-0">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Login / Continue on Website
                </a>
            </div>
            <div class="text-center pb-3">
                <button class="btn btn-link nx-text-secondary small text-decoration-none" data-bs-dismiss="modal">Keep browsing</button>
            </div>
        </div>
    </div>
</div>

@auth
    @if(auth()->user()->isClient())
        @include('partials.booking.service-picker-modal')
    @endif
@endauth

{{-- Footer shows only on the Guest Home/landing page — never on the Sign In
     / Create Account / password-reset pages, never on Services/Packages/
     Offers/About/Contact (their own content already ends with what a Guest
     needs; the footer was just dead weight below the fold there), and never
     for any signed-in user (Client Website or Management) — the account/
     admin nav already covers those links. The Dialogflow AI widget further
     down is unaffected by this condition. --}}
@if(auth()->guest() && !request()->routeIs('login', 'login.attempt', 'register', 'register.attempt', 'password.request', 'password.email', 'password.reset', 'password.update', 'verification.show', 'verification.verify', 'verification.resend', 'services', 'packages', 'offers', 'about', 'contact'))
<footer class="nx-footer">
    <div class="container d-flex flex-wrap justify-content-between gap-4 py-5">
        <div style="max-width: 320px;">
            <p class="nx-font-display mb-2" style="font-size: 24px; color: #f8f4ef;">Perfect Nails</p>
            <p class="nx-footer-tagline mb-0">Wellness &amp; Aesthetics — 237 A. Mabini St., Maypajo, Caloocan</p>
        </div>
        <div>
            <h6>Services</h6>
            <ul class="list-unstyled d-flex flex-column gap-2 mb-0 mt-2">
                <li><a href="{{ route('services') }}">Nails &amp; Eyes</a></li>
                <li><a href="{{ route('services') }}">Massage</a></li>
                <li><a href="{{ route('services') }}">Aesthetics</a></li>
                <li><a href="{{ route('services') }}">Home Service</a></li>
            </ul>
        </div>
        <div>
            <h6>Company</h6>
            <ul class="list-unstyled d-flex flex-column gap-2 mb-0 mt-2">
                <li><a href="{{ route('about') }}">About us</a></li>
                <li><a href="{{ route('packages') }}">Packages</a></li>
                <li><a href="{{ route('contact') }}">Contact us</a></li>
            </ul>
        </div>
        <div>
            <h6>Booking</h6>
            <ul class="list-unstyled d-flex flex-column gap-2 mb-0 mt-2">
                {{-- This footer only ever renders for Guests now (see the
                     @if above) — the authenticated-Client branch that used
                     to live here is unreachable since the footer stopped
                     showing for any signed-in user, so it's removed rather
                     than kept as dead code. --}}
                <li><a href="#" data-bs-toggle="modal" data-bs-target="#getAppModal">Get the App</a></li>
                <li><a href="{{ route('login') }}">Management Login</a></li>
            </ul>
        </div>
        <div>
            <h6>Policies</h6>
            <ul class="list-unstyled d-flex flex-column gap-2 mb-0 mt-2">
                {{-- Previously linked to the now-unlisted /faqs page; repointed to
                     Contact (an existing named route) so these aren't dead ends. --}}
                <li><a href="{{ route('contact') }}">Booking &amp; grace period</a></li>
                <li><a href="{{ route('contact') }}">Cancellation</a></li>
            </ul>
        </div>
    </div>
    <hr class="nx-footer-divider m-0">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 py-3">
        <small class="nx-footer-bottom">&copy; {{ date('Y') }} Perfect Nails Wellness &amp; Aesthetics. All rights reserved.</small>
        <div class="d-flex gap-2">
            <span class="nx-social">FB</span>
            <span class="nx-social">IG</span>
        </div>
    </div>
</footer>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Book Appointment modal: honor a trigger's data-booking-redirect (see
    // catalog/detail.blade.php) by pointing the "Continue on Website" login
    // link at that specific booking URL instead of the default (the page
    // the modal was opened from). Falls back to the server-rendered default
    // whenever a trigger doesn't set the attribute — every other trigger
    // site-wide is unaffected.
    (function () {
        const modal = document.getElementById('getAppModal');
        const loginLink = document.getElementById('getAppModalLoginLink');
        if (!modal || !loginLink) return;

        const defaultHref = loginLink.getAttribute('href');
        const loginBase = defaultHref.split('?')[0];

        modal.addEventListener('show.bs.modal', (event) => {
            const redirect = event.relatedTarget?.dataset?.bookingRedirect;
            loginLink.href = redirect
                ? `${loginBase}?redirect=${encodeURIComponent(redirect)}`
                : defaultHref;
        });
    })();

    // Dark-mode toggle (persisted in localStorage).
    (function () {
        const btn = document.getElementById('themeToggle');
        if (!btn) return;
        const icon = btn.querySelector('i');
        const sync = () => {
            const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            icon.className = dark ? 'bi bi-sun' : 'bi bi-moon-stars';
        };
        sync();
        btn.addEventListener('click', () => {
            const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            const next = dark ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', next);
            localStorage.setItem('theme', next);
            sync();
        });
    })();

    // Show/hide password toggles — any auth form field marked with
    // data-password-field="<input id>" gets an eye button next to it. One
    // delegated setup so every form (login, register, reset) shares it.
    (function () {
        document.querySelectorAll('[data-password-field]').forEach((input) => {
            const button = input.parentElement.querySelector('[data-password-toggle]');
            if (!button) return;

            const icon = button.querySelector('i');
            const toggle = () => {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            };

            button.addEventListener('click', toggle);
        });
    })();
</script>

@if(config('services.dialogflow.agent_id'))
    {{-- Dialogflow ES inquiry bot --}}
    <script src="https://www.gstatic.com/dialogflow-console/fast/messenger/bootstrap.js?v=1"></script>
    <df-messenger
        intent="WELCOME"
        chat-title="Perfect Nails"
        agent-id="{{ config('services.dialogflow.agent_id') }}"
        language-code="en"
        chat-icon="https://fonts.gstatic.com/s/i/materialicons/spa/v12/24px.svg">
    </df-messenger>
    <style>
        df-messenger {
            --df-messenger-button-titlebar-color: #3D2817;
            --df-messenger-send-icon: #9C7A54;
        }
    </style>
@endif

@stack('scripts')
</body>
</html>
