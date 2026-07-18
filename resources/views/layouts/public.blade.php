<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Perfect Nails Wellness and Aesthetics')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --spa-cream: #FBF7F2;   /* warm ivory/latte page bg */
            --spa-ivory: #FFFFFF;
            --spa-tan: #EAD9C7;     /* latte tint / borders */
            --spa-latte: #B5895A;   /* caramel (ombré start) */
            --spa-gold: #A97C50;    /* caramel-bronze primary */
            --spa-metallic: #C9A24B;/* metallic gold accent */
            --spa-espresso: #3A2317;/* dark / premium */
            --spa-text: #2C1E14;    /* deep espresso text */
            --spa-heading: #3A2317; /* heading color (flips light in dark mode) */
            --spa-blush: #D9A7A0;   /* sparing CTA pop */
            --spa-sage: #8FA68A;    /* sparing CTA pop */
        }
        /* Dark mode: remap surfaces + text; keep espresso/gold for banners. */
        html[data-bs-theme="dark"] {
            --spa-cream: #14100D;
            --spa-ivory: #201812;
            --spa-tan: #3A2E24;
            --spa-text: #F0E7DD;
            --spa-heading: #F0E7DD;
            --spa-gold: #D9B070;
            --spa-metallic: #E0BD77;
        }
        html[data-bs-theme="dark"] .bg-white { background: var(--spa-ivory) !important; }
        html[data-bs-theme="dark"] .text-muted { color: #b9a894 !important; }
        html[data-bs-theme="dark"] .navbar-spa { background: rgba(20,16,13,.92); }
        html[data-bs-theme="dark"] .card-spa { background: var(--spa-ivory); border-color: var(--spa-tan); }
        html[data-bs-theme="dark"] .btn-outline-spa { border-color: var(--spa-text); color: var(--spa-text); }
        html[data-bs-theme="dark"] .btn-outline-spa:hover { background: var(--spa-text); color: var(--spa-cream); }
        html[data-bs-theme="dark"] .nav-pills .nav-link { color: var(--spa-text) !important; }
        html[data-bs-theme="dark"] .navbar-brand, html[data-bs-theme="dark"] .navbar-nav .nav-link { color: var(--spa-text); }
        body { font-family: 'Montserrat', sans-serif; background: var(--spa-cream); color: var(--spa-text); }
        h1, h2, h3, .brand-font { font-family: 'Playfair Display', serif; color: var(--spa-heading); }
        .bg-ombre { background: linear-gradient(135deg, var(--spa-latte) 0%, var(--spa-espresso) 100%); }
        .btn-spa { background: var(--spa-espresso); color: #fff; border: none; transition: background .2s, transform .2s; }
        .btn-spa:hover { background: var(--spa-gold); color: #fff; transform: translateY(-1px); }
        .btn-outline-spa { border: 1px solid var(--spa-espresso); color: var(--spa-espresso); background: transparent; transition: all .2s; }
        .btn-outline-spa:hover { background: var(--spa-espresso); color: #fff; }
        .btn-gold { background: var(--spa-metallic); color: var(--spa-espresso); border: none; font-weight: 600; transition: transform .2s, filter .2s; }
        .btn-gold:hover { filter: brightness(1.05); transform: translateY(-1px); color: var(--spa-espresso); }
        .text-gold { color: var(--spa-gold) !important; }
        .text-metallic { color: var(--spa-metallic) !important; }
        .bg-espresso { background: var(--spa-espresso); }
        .gold-divider { display:inline-block; width: 56px; height: 2px; background: var(--spa-metallic); border: 0; opacity: 1; border-radius: 2px; }
        .card-spa { background: var(--spa-ivory); border: 1px solid var(--spa-tan); border-radius: 1rem; transition: transform .2s, box-shadow .2s; }
        .card-spa:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(58,35,23,.12); }
        .navbar-spa { background: rgba(251,247,242,.92); backdrop-filter: blur(8px); border-bottom: 1px solid var(--spa-tan); }
        .nav-pills .nav-link { color: var(--spa-espresso); }
        .nav-pills .nav-link.active { background: var(--spa-espresso) !important; color: #fff; }
    </style>
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
<body>
<nav class="navbar navbar-expand-lg navbar-spa sticky-top">
    <div class="container">
        <a class="navbar-brand brand-font fw-bold" href="{{ route('landing') }}">
            Perfect <span class="text-gold">Nails</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="{{ route('landing') }}#services">Services</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('landing') }}#products">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('landing') }}#about">About Us</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('landing') }}#contact">Contact</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('landing') }}#app">Get the App</a></li>
                <li class="nav-item">
                    <button type="button" id="themeToggle" class="btn btn-outline-spa btn-sm px-2" title="Toggle dark mode" aria-label="Toggle dark mode">
                        <i class="bi bi-moon-stars"></i>
                    </button>
                </li>
                <li class="nav-item">
                    <a class="btn btn-outline-spa btn-sm px-3" href="{{ route('login') }}">Management Login</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

@yield('content')

{{-- GET-THE-APP MODAL (booking happens in the mobile app) --}}
<div class="modal fade" id="getAppModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 1.25rem; overflow: hidden;">
            <div class="bg-ombre text-white text-center p-4">
                <i class="bi bi-phone fs-1"></i>
                <h4 class="brand-font mt-2 mb-1" style="color:#fff;">Book in the Perfect Nails app</h4>
                <p class="small opacity-75 mb-0">Booking, payment &amp; tracking all happen in the app.</p>
            </div>
            <div class="modal-body text-center p-4">
                <p class="text-muted mb-4">Download the app to pick your specialist, choose a slot, and confirm your booking.</p>
                <div class="d-grid gap-2">
                    <a href="#" class="btn btn-spa btn-lg"><i class="bi bi-android2 me-2"></i>Download for Android</a>
                    <a href="#" class="btn btn-outline-spa btn-lg"><i class="bi bi-apple me-2"></i>Coming to iOS</a>
                </div>
            </div>
            <div class="text-center pb-3">
                <button class="btn btn-link text-muted small text-decoration-none" data-bs-dismiss="modal">Keep browsing</button>
            </div>
        </div>
    </div>
</div>

<footer class="bg-espresso text-white py-4 mt-5">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <span class="brand-font">Perfect Nails Wellness and Aesthetics</span>
        <small class="opacity-75">&copy; {{ date('Y') }} NEXFLIO Business Management System</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
