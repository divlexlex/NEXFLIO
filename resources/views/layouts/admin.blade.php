<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · NEXFLIO Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --spa-cream: #FBF7F2;
            --spa-tan: #EAD9C7;
            --spa-latte: #B5895A;
            --spa-gold: #A97C50;
            --spa-metallic: #C9A24B;
            --spa-espresso: #3A2317;
            --spa-text: #2C1E14;
        }
        /* Dark mode: Bootstrap's data-bs-theme handles cards/tables/forms;
           we just remap the page surfaces. Sidebar is already dark. */
        html[data-bs-theme="dark"] {
            --spa-cream: #14100D;
            --spa-tan: #3A2E24;
            --spa-text: #F0E7DD;
        }
        body { background: var(--spa-cream); color: var(--spa-text); }
        .sidebar {
            width: 240px; min-height: 100vh;
            background: linear-gradient(180deg, #3A2317 0%, #2A190F 100%);
            position: fixed; top: 0; left: 0; overflow-y: auto;
        }
        .sidebar .nav-link { color: rgba(255,255,255,.75); border-radius: .5rem; transition: background .15s, color .15s; }
        .sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,.08); }
        .sidebar .nav-link.active { color: #fff; background: var(--spa-gold); }
        .main-content { margin-left: 240px; padding: 1.5rem; }
        .card { border: 1px solid var(--spa-tan); border-radius: .75rem; }
        .text-gold { color: var(--spa-gold); }
        .btn-spa { background: var(--spa-espresso); color: #fff; transition: background .2s; }
        .btn-spa:hover { background: var(--spa-gold); color: #fff; }
        @media (max-width: 991px) {
            .sidebar { position: static; width: 100%; min-height: auto; }
            .main-content { margin-left: 0; }
        }
    </style>
    <script>
        (function () {
            const saved = localStorage.getItem('theme');
            const theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    @stack('head')
</head>
<body>
<div class="d-lg-flex">
    <aside class="sidebar p-3">
        <a href="{{ route('admin.dashboard') }}" class="d-block text-white text-decoration-none fs-5 fw-bold mb-4 px-2">
            NEXFLIO <small class="d-block fs-6 fw-normal opacity-50">Perfect Nails Admin</small>
        </a>
        <ul class="nav flex-column gap-1">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.appointments') ? 'active' : '' }}" href="{{ route('admin.appointments') }}">
                    <i class="bi bi-calendar-event me-2"></i>Appointments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.payments') ? 'active' : '' }}" href="{{ route('admin.payments') }}">
                    <i class="bi bi-cash-coin me-2"></i>Payments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.inventory') ? 'active' : '' }}" href="{{ route('admin.inventory') }}">
                    <i class="bi bi-box-seam me-2"></i>Inventory
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.employees') ? 'active' : '' }}" href="{{ route('admin.employees') }}">
                    <i class="bi bi-people me-2"></i>Employees
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.leaves') ? 'active' : '' }}" href="{{ route('admin.leaves') }}">
                    <i class="bi bi-calendar-x me-2"></i>Leave Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.billing') ? 'active' : '' }}" href="{{ route('admin.billing') }}">
                    <i class="bi bi-receipt me-2"></i>Billing
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.services') ? 'active' : '' }}" href="{{ route('admin.services') }}">
                    <i class="bi bi-stars me-2"></i>Services
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.promos') ? 'active' : '' }}" href="{{ route('admin.promos') }}">
                    <i class="bi bi-tags me-2"></i>Promos
                </a>
            </li>
            @if((int) auth()->user()->role_id === \App\Models\User::ROLE_SUPER_ADMIN)
                <li class="mt-3 px-2 text-uppercase small text-white-50">Owner</li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.audit-logs') ? 'active' : '' }}" href="{{ route('admin.audit-logs') }}">
                        <i class="bi bi-shield-lock me-2"></i>Audit Trail
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.reports.financial') ? 'active' : '' }}" href="{{ route('admin.reports.financial') }}">
                        <i class="bi bi-graph-up-arrow me-2"></i>Financial Reports
                    </a>
                </li>
            @endif
        </ul>
        <hr class="border-secondary">
        <div class="px-2 text-white-50 small mb-2">
            {{ auth()->user()->name }}
            ({{ (int) auth()->user()->role_id === \App\Models\User::ROLE_SUPER_ADMIN ? 'Owner' : 'Manager' }})
        </div>
        <button type="button" id="themeToggle" class="btn btn-sm btn-outline-light w-100 mb-2 mx-2" style="width:calc(100% - 1rem);">
            <i class="bi bi-moon-stars me-1"></i><span id="themeToggleLabel">Dark mode</span>
        </button>
        <form method="POST" action="{{ route('logout') }}" class="px-2">
            @csrf
            <button class="btn btn-sm btn-outline-light w-100"><i class="bi bi-box-arrow-right me-1"></i>Log out</button>
        </form>
    </aside>

    <main class="main-content flex-grow-1">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const btn = document.getElementById('themeToggle');
        if (!btn) return;
        const label = document.getElementById('themeToggleLabel');
        const icon = btn.querySelector('i');
        const sync = () => {
            const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            icon.className = dark ? 'bi bi-sun me-1' : 'bi bi-moon-stars me-1';
            if (label) label.textContent = dark ? 'Light mode' : 'Dark mode';
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
@stack('scripts')
</body>
</html>
