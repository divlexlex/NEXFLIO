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
        ['label' => 'Packages', 'route' => 'packages', 'href' => route('packages')],
        ['label' => 'Offers', 'route' => 'offers', 'href' => route('offers')],
        ['label' => 'About', 'route' => 'about', 'href' => route('about')],
        // Gallery and FAQs intentionally left out of primary navigation for now
        // (routes/views/config still exist at /gallery and /faqs — see PageController
        // and config/gallery.php + config/faqs.php — just not linked from here).
        ['label' => 'Contact', 'route' => 'contact', 'href' => route('contact')],
    ];

    // Logged-in Client Website nav: Dashboard + Appointments replace Home,
    // Services/Packages stay since Browse is still a core Client action.
    // About/Contact are one click away via the footer, so dropping them
    // here keeps the authenticated bar from feeling like Guest nav + extras.
    $clientLinks = [
        ['label' => 'Dashboard', 'route' => 'account.dashboard', 'href' => route('account.dashboard')],
        ['label' => 'Appointments', 'route' => 'account.bookings', 'href' => route('account.bookings')],
        ['label' => 'Services', 'route' => 'services', 'href' => route('services')],
        ['label' => 'Packages', 'route' => 'packages', 'href' => route('packages')],
        ['label' => 'Offers', 'route' => 'offers', 'href' => route('offers')],
    ];

    $navLinks = $isClient ? $clientLinks : $guestLinks;
    // The logo always goes to the landing page — Dashboard already has its
    // own nav item above for a logged-in Client, so the logo stays a
    // consistent "take me home" action for everyone, logged in or not.
    $logoHref = route('landing');
@endphp
<nav class="navbar navbar-expand-lg nx-header sticky-top">
    <div class="container py-2">
        <a class="navbar-brand" href="{{ $logoHref }}">
            Perfect <span class="nx-text-accent">Nails</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="publicNav">
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
                <li class="nav-item">
                    <button type="button" id="themeToggle" class="btn btn-link text-decoration-none p-2" title="Toggle dark mode" aria-label="Toggle dark mode" style="color: var(--nx-text-primary);">
                        <i class="bi bi-moon-stars"></i>
                    </button>
                </li>
                @auth
                    @if(auth()->user()->isManagerOrAbove())
                        {{-- Management stays in their existing, unredesigned dashboard —
                             never shown as "Guest" once authenticated. --}}
                        <li class="nav-item">
                            <a class="nx-btn nx-btn-dark" href="{{ route('admin.dashboard') }}">
                                <i class="bi bi-speedometer2 me-1"></i>Dashboard
                            </a>
                        </li>
                    @else
                        {{-- Client Account dropdown. Dashboard and Appointments (My
                             Bookings) are already primary nav items above, so the
                             dropdown only holds what doesn't have its own nav slot —
                             Profile — plus Logout, per the "avoid duplicate nav"
                             guidance. Label uses the real authenticated Client's
                             first name (never a hardcoded placeholder). --}}
                        <li class="nav-item dropdown">
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
                        </li>
                    @endif
                @else
                    <li class="nav-item">
                        <a class="nx-btn nx-btn-dark" href="{{ route('login') }}">
                            <i class="bi bi-person-circle me-1"></i>Guest
                        </a>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>
