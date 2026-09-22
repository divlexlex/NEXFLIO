<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · NEXFLIO Staff</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        {{-- Same design tokens as layouts.admin, kept in sync deliberately —
             Staff is a smaller, single-page portal, not a role variant of
             the Admin/Manager sidebar (which stays gated to role:1,2). --}}
        :root {
            --spa-cream: #FBF7F2;
            --spa-tan: #EAD9C7;
            --spa-latte: #B5895A;
            --spa-gold: #A97C50;
            --spa-metallic: #C9A24B;
            --spa-espresso: #3A2317;
            --spa-text: #2C1E14;
        }
        html[data-bs-theme="dark"] {
            --spa-cream: #14100D;
            --spa-tan: #3A2E24;
            --spa-text: #F0E7DD;
        }
        body { background: var(--spa-cream); color: var(--spa-text); }
        .sidebar {
            width: 240px; height: 100vh;
            background: linear-gradient(180deg, #3A2317 0%, #2A190F 100%);
            position: fixed; top: 0; left: 0;
            display: flex; flex-direction: column;
        }
        .sidebar .nav-link { color: rgba(255,255,255,.75); border-radius: .5rem; transition: background .15s, color .15s; }
        .sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,.08); }
        .sidebar .nav-link.active { color: #fff; background: var(--spa-gold); }
        .main-content { margin-left: 240px; padding: 1.5rem; }
        .card { border: 1px solid var(--spa-tan); border-radius: .75rem; }
        .text-gold { color: var(--spa-gold); }
        .btn-spa { background: var(--spa-espresso); color: #fff; transition: background .2s; }
        .btn-spa:hover { background: var(--spa-gold); color: #fff; }
        html[data-bs-theme="dark"] .btn-spa { background: var(--spa-gold); }
        html[data-bs-theme="dark"] .btn-spa:hover { background: var(--spa-metallic); }
        @media (max-width: 991px) {
            .sidebar { position: static; width: 100%; height: auto; }
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
        <a href="{{ route('staff.dashboard') }}" class="d-block text-white text-decoration-none fs-5 fw-bold mb-4 px-2">
            NEXFLIO <small class="d-block fs-6 fw-normal opacity-50">Perfect Nails Staff</small>
        </a>
        <ul class="nav flex-column gap-1">
            <li class="nav-item">
                <a class="nav-link active" href="{{ route('staff.dashboard') }}">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
            </li>
        </ul>
        <hr class="border-secondary">
        <div class="px-2 text-white-50 small mb-2">
            {{ auth()->user()->name }}
            <span class="d-block">{{ auth()->user()->staffProfile?->position ?? 'Staff' }}</span>
        </div>
        <button type="button" class="btn btn-sm btn-outline-light w-100 mb-2 mx-2" style="width:calc(100% - 1rem);"
                data-bs-toggle="modal" data-bs-target="#changePasswordModal">
            <i class="bi bi-key me-1"></i>Change Password
        </button>
        <button type="button" id="themeToggle" class="btn btn-sm btn-outline-light w-100 mb-2 mx-2" style="width:calc(100% - 1rem);">
            <i class="bi bi-moon-stars me-1"></i><span id="themeToggleLabel">Dark mode</span>
        </button>
        <form method="POST" action="{{ route('logout') }}" class="px-2">
            @csrf
            <button class="btn btn-sm btn-outline-light w-100"><i class="bi bi-box-arrow-right me-1"></i>Log out</button>
        </form>
    </aside>

    {{-- CHANGE PASSWORD — every Staff account starts on an Admin-generated
         temporary password (see EmployeeController::store()); this is how
         they set their own. Lives in the layout (not the dashboard view)
         so it's reachable from any future Staff page. --}}
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('staff.password.update') }}" class="modal-content">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control" minlength="8" required>
                        <div class="form-text">At least 8 characters.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-spa">Update Password</button>
                </div>
            </form>
        </div>
    </div>

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
