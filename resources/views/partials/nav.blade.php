{{-- Shared public website navbar. Included by layouts.public so every
     public-facing page gets the same nav, active-state highlighting, and
     Guest/Client/Management entry — but the LINK SET itself now differs for
     an authenticated Client vs. everyone else (Guest, or Management
     browsing the public site), per the Client Website structure refinement:
     the logged-in Client Website is meant to feel meaningfully different
     from Guest browsing, not just "Guest nav + a name."

     "Home" is intentionally not a nav item for anyone — the landing page
     itself is untouched and still lives at route('landing'); it's reached
     via the logo instead (see $logoHref below), never via a nav label. --}}
@php
    $isClient = auth()->check() && auth()->user()->isClient();

    // Guest and Management-browsing-the-public-site share this set — Home
    // was removed as a nav label (not as a page/route/section) per the
    // Guest navigation refinement. Management's own destination is still
    // the existing admin dashboard button below, unaffected.
    $guestLinks = [
        ['label' => 'Services', 'route' => 'services', 'href' => route('services')],
        ['label' => 'Promo', 'route' => 'offers', 'href' => route('offers')],
        ['label' => 'About', 'route' => 'about', 'href' => route('about')],
        // Gallery and FAQs intentionally left out of primary navigation for now
        // (routes/views/config still exist at /gallery and /faqs — see PageController
        // and config/gallery.php + config/faqs.php — just not linked from here).
        ['label' => 'Contact', 'route' => 'contact', 'href' => route('contact')],
    ];

    // Logged-in Client Website nav: Dashboard + Appointments replace Home,
    // Services/Promo stay since Browse is still a core Client action.
    // About/Contact are one click away via the footer, so dropping them
    // here keeps the authenticated bar from feeling like Guest nav + extras.
    $clientLinks = [
        ['label' => 'Dashboard', 'route' => 'account.dashboard', 'href' => route('account.dashboard')],
        ['label' => 'Appointments', 'route' => 'account.bookings', 'href' => route('account.bookings')],
        ['label' => 'Services', 'route' => 'services', 'href' => route('services')],
        ['label' => 'Promo', 'route' => 'offers', 'href' => route('offers')],
    ];

    $navLinks = $isClient ? $clientLinks : $guestLinks;
    // The logo always goes to the landing page — Dashboard already has its
    // own nav item above for a logged-in Client, so the logo stays a
    // consistent "take me home" action for everyone, logged in or not.
    $logoHref = route('landing');
@endphp
<nav class="navbar navbar-expand-lg nx-header sticky-top">
    <div class="container py-2">
        <a class="navbar-brand nx-brand-lockup" href="{{ $logoHref }}" aria-label="Perfect Nails — home">
            <span class="nx-brand-words">
                <span class="nx-brand-word">Perfect</span>
                <span class="nx-brand-script">Nails</span>
            </span>
            <span class="nx-brand-sub">Wellness &amp; Aesthetics</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="publicNav">
            <div class="nx-drawer-head d-lg-none">
                @auth
                    @if(auth()->user()->isManagerOrAbove())
                        {{-- Management stays in their existing, unredesigned dashboard —
                             never shown as "Guest" once authenticated. --}}
                        <a class="nx-btn nx-btn-dark" href="{{ route('admin.dashboard') }}">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    @else
                        {{-- Client Account dropdown. Dashboard and Appointments (My
                             Bookings) are already primary nav items above, so the
                             dropdown only holds what doesn't have its own nav slot —
                             Profile — plus Logout, per the "avoid duplicate nav"
                             guidance. Label uses the real authenticated Client's
                             first name (never a hardcoded placeholder). --}}
                        <div class="dropdown">
                            <a class="nx-btn nx-btn-dark dropdown-toggle" href="#" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i>{{ explode(' ', auth()->user()->name)[0] }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end nx-account-menu">
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('account.profile') ? 'active' : '' }}" href="{{ route('account.profile') }}">
                                        <i class="bi bi-person me-2"></i>Profile
                                    </a>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item" id="themeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
                                        <i class="bi bi-moon-stars me-2"></i>Dark mode
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item">
                                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @endif
                @else
                    <a class="nx-btn nx-btn-dark" href="{{ route('login') }}">
                        <i class="bi bi-person-circle me-1"></i>Guest
                    </a>
                @endauth
                @if(auth()->check() && auth()->user()->isClient())
                    {{-- Notifications bell (Design C): latest 6 + unread badge,
                         supplied by the partials.nav view composer in
                         AppServiceProvider as $navNotifications /
                         $navUnreadCount. --}}
                    <div class="dropdown">
                        <button type="button" id="notificationBell" class="btn btn-link text-decoration-none p-2 position-relative" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications" aria-label="Notifications" style="color: var(--nx-text-primary);">
                            <i class="bi bi-bell"></i>
                            @if($navUnreadCount > 0)
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 10px;">{{ $navUnreadCount }}</span>
                            @endif
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end nx-account-menu nx-notif-menu" style="min-width: 330px;">
                            <li class="d-flex justify-content-between align-items-center px-3 py-2">
                                <span class="small fw-semibold">Notifications</span>
                                @if($navUnreadCount > 0)
                                    <form method="POST" action="{{ route('account.notifications.read-all') }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn btn-link btn-sm text-decoration-none p-0" style="color: var(--nx-primary);">
                                            Mark all read
                                        </button>
                                    </form>
                                @endif
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            @if($navNotifications->isEmpty())
                                <li><p class="small nx-text-secondary mb-0 px-3 py-2">You're all caught up — no notifications yet.</p></li>
                            @else
                                <div class="nx-notif-scroll">
                                    @foreach($navNotifications as $notification)
                                        <li class="nx-notif-item px-3 py-2">
                                            <div class="nx-notification {{ $notification->read_at ? 'is-read' : '' }}">
                                                <span class="nx-notification-dot"></span>
                                                <div class="flex-grow-1">
                                                    <p class="small fw-semibold mb-0 nx-notification-title">{{ $notification->title }}</p>
                                                    <p class="small nx-text-secondary mb-1">{{ $notification->body }}</p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="small nx-text-secondary" style="font-size: 12px;">{{ $notification->created_at->diffForHumans() }}</span>
                                                        @unless($notification->read_at)
                                                            <form method="POST" action="{{ route('account.notifications.read', $notification->id) }}">
                                                                @csrf
                                                                @method('PATCH')
                                                                <button type="submit" class="btn btn-link btn-sm text-decoration-none p-0" style="font-size: 12px; color: var(--nx-primary);">
                                                                    Mark read
                                                                </button>
                                                            </form>
                                                        @endunless
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </div>
                            @endif
                        </ul>
                    </div>
                @endif
                <button type="button" class="nx-drawer-close" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-label="Close menu"><i class="bi bi-x-lg"></i></button>
            </div>
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-4">
                @foreach($navLinks as $link)
                    <li class="nav-item">
                        <a class="nav-link {{ $link['route'] && request()->routeIs($link['route']) ? 'active' : '' }}"
                           href="{{ $link['href'] }}"
                           @if($link['route'] && request()->routeIs($link['route'])) aria-current="page" @endif>
                            {{ $link['label'] }}
                        </a>
                    </li>
                @endforeach
                @if(!$isClient)
                    {{-- Standalone toggle for guests and authenticated
                         non-clients (no account dropdown there). Clients get
                         the toggle inside their account dropdown instead, so
                         exactly one #themeToggle exists in every state. --}}
                    <li class="nav-item">
                        <button type="button" id="themeToggle" class="btn btn-link text-decoration-none p-2" title="Toggle dark mode" aria-label="Toggle dark mode" style="color: var(--nx-text-primary);">
                            <i class="bi bi-moon-stars"></i>
                        </button>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</nav>
