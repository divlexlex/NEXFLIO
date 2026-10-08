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
            --nex-bg: #FAF5EE;
            --nex-surface: #FFFFFF;
            --nex-raised: #FFFFFF;
            --nex-sidebar-bg: #2E1B10;
            --nex-sidebar-bg-end: #20140B;
            --nex-sidebar-text: #F3E7D7;
            --nex-sidebar-muted: rgba(243, 231, 215, .74);
            --nex-sidebar-border: rgba(243, 231, 215, .16);
            --nex-sidebar-active-bg: #C9A24B;
            --nex-sidebar-active-text: #2E1B10;
            --nex-text: #2B1A12;
            --nex-muted: #71604F;
            --nex-border: #E4D5C3;
            --nex-table-header-bg: #F3E9DC;
            --nex-table-header-text: #2B1A12;
            --nex-zebra: #FCF8F2;
            --nex-primary-bg: #A97C50;
            --nex-primary-text: #FFFFFF;
            --nex-primary-hover: #8F653B;
            --nex-accent: #B5895A;
            --nex-gold: #C9A24B;
            --nex-link: #8A5A2B;
            --nex-link-hover: #6E4520;
            --nex-success-bg: #E2EFD9;
            --nex-success-text: #1E4620;
            --nex-success-border: #BFD9B0;
            --nex-warning-bg: #F2C230;
            --nex-warning-text: #3A2A05;
            --nex-warning-border: #DCAB1C;
            --nex-warning-strong: #7A5600;
            --nex-warning-subtle-bg: #FBF0D4;
            --nex-warning-subtle-text: #5B3E00;
            --nex-warning-subtle-border: #E8D398;
            --nex-danger-bg: #F7DDD9;
            --nex-danger-text: #84261B;
            --nex-danger-border: #E8B4AC;
        }
        html[data-bs-theme="dark"] {
            --nex-bg: #17100B;
            --nex-surface: #241811;
            --nex-raised: #2E2015;
            --nex-sidebar-bg: #120B07;
            --nex-sidebar-bg-end: #0A0603;
            --nex-sidebar-text: #F3E7D7;
            --nex-sidebar-muted: rgba(243, 231, 215, .72);
            --nex-sidebar-border: rgba(243, 231, 215, .12);
            --nex-sidebar-active-bg: #C9A24B;
            --nex-sidebar-active-text: #2E1B10;
            --nex-text: #F5EBDD;
            --nex-muted: #C8AE94;
            --nex-border: #463322;
            --nex-table-header-bg: #2E2015;
            --nex-table-header-text: #F5EBDD;
            --nex-zebra: #291B11;
            --nex-primary-bg: #C9A24B;
            --nex-primary-text: #241811;
            --nex-primary-hover: #E0BC63;
            --nex-accent: #B5895A;
            --nex-gold: #C9A24B;
            --nex-link: #D9B070;
            --nex-link-hover: #E8C888;
            --nex-success-bg: #1F3A1F;
            --nex-success-text: #B8E0B8;
            --nex-success-border: #3F6B3F;
            --nex-warning-bg: #F2C230;
            --nex-warning-text: #3A2A05;
            --nex-warning-border: #C9A24B;
            --nex-warning-strong: #F2C230;
            --nex-warning-subtle-bg: #33250B;
            --nex-warning-subtle-text: #F2C230;
            --nex-warning-subtle-border: #6B5215;
            --nex-danger-bg: #40160F;
            --nex-danger-text: #F1B8AE;
            --nex-danger-border: #7A2E23;
        }

        :root {
            --bs-body-bg: var(--nex-bg);
            --bs-body-color: var(--nex-text);
            --bs-secondary-color: var(--nex-muted);
            --bs-secondary-bg: var(--nex-zebra);
            --bs-tertiary-bg: var(--nex-zebra);
            --bs-emphasis-color: var(--nex-text);
            --bs-border-color: var(--nex-border);
            --bs-border-color-translucent: var(--nex-border);
            --bs-card-bg: var(--nex-surface);
            --bs-card-color: var(--nex-text);
            --bs-card-border-color: var(--nex-border);
            --bs-card-cap-bg: var(--nex-zebra);
            --bs-modal-bg: var(--nex-surface);
            --bs-modal-color: var(--nex-text);
            --bs-modal-border-color: var(--nex-border);
            --bs-dropdown-bg: var(--nex-surface);
            --bs-dropdown-color: var(--nex-text);
            --bs-dropdown-border-color: var(--nex-border);
            --bs-dropdown-link-color: var(--nex-text);
            --bs-dropdown-link-hover-bg: var(--nex-zebra);
            --bs-dropdown-link-hover-color: var(--nex-text);
            --bs-dropdown-link-active-bg: var(--nex-primary-bg);
            --bs-dropdown-link-active-color: var(--nex-primary-text);
            --bs-list-group-bg: var(--nex-surface);
            --bs-list-group-color: var(--nex-text);
            --bs-list-group-border-color: var(--nex-border);
            --bs-list-group-action-hover-bg: var(--nex-zebra);
            --bs-list-group-action-active-bg: var(--nex-zebra);
            --bs-light: var(--nex-surface);
            --bs-light-rgb: 255, 255, 255;
            --bs-dark: var(--nex-sidebar-bg);
            --bs-dark-rgb: 46, 27, 16;
            --bs-primary: var(--nex-primary-bg);
            --bs-primary-rgb: 169, 124, 80;
            --bs-link-color: var(--nex-link);
            --bs-link-hover-color: var(--nex-link-hover);
            --bs-link-color-rgb: 138, 90, 43;
            --bs-link-hover-color-rgb: 110, 69, 32;
            --bs-focus-ring-color: rgba(169, 124, 80, .4);
            --bs-nav-pills-link-active-bg: var(--nex-primary-bg);
            --bs-nav-pills-link-active-color: var(--nex-primary-text);
            --bs-progress-bar-bg: var(--nex-primary-bg);
            --bs-table-color: var(--nex-text);
            --bs-table-border-color: var(--nex-border);
            --bs-table-striped-bg: var(--nex-zebra);
            --bs-table-striped-color: var(--nex-text);
            --bs-table-hover-bg: var(--nex-zebra);
            --bs-table-hover-color: var(--nex-text);
            --bs-table-active-bg: var(--nex-zebra);
            --bs-table-active-color: var(--nex-text);
            --bs-success-bg-subtle: var(--nex-success-bg);
            --bs-success-text: var(--nex-success-text);
            --bs-success-border-subtle: var(--nex-success-border);
            --bs-warning-bg-subtle: var(--nex-warning-subtle-bg);
            --bs-warning-text: var(--nex-warning-subtle-text);
            --bs-warning-border-subtle: var(--nex-warning-subtle-border);
            --bs-danger-bg-subtle: var(--nex-danger-bg);
            --bs-danger-text: var(--nex-danger-text);
            --bs-danger-border-subtle: var(--nex-danger-border);
        }
        html[data-bs-theme="dark"] {
            --bs-body-bg: var(--nex-bg);
            --bs-body-color: var(--nex-text);
            --bs-secondary-color: var(--nex-muted);
            --bs-secondary-bg: var(--nex-raised);
            --bs-tertiary-bg: var(--nex-raised);
            --bs-emphasis-color: var(--nex-text);
            --bs-border-color: var(--nex-border);
            --bs-border-color-translucent: var(--nex-border);
            --bs-card-bg: var(--nex-surface);
            --bs-card-color: var(--nex-text);
            --bs-card-border-color: var(--nex-border);
            --bs-card-cap-bg: var(--nex-zebra);
            --bs-modal-bg: var(--nex-surface);
            --bs-modal-color: var(--nex-text);
            --bs-modal-border-color: var(--nex-border);
            --bs-dropdown-bg: var(--nex-surface);
            --bs-dropdown-color: var(--nex-text);
            --bs-dropdown-border-color: var(--nex-border);
            --bs-dropdown-link-color: var(--nex-text);
            --bs-dropdown-link-hover-bg: var(--nex-raised);
            --bs-dropdown-link-hover-color: var(--nex-text);
            --bs-dropdown-link-active-bg: var(--nex-primary-bg);
            --bs-dropdown-link-active-color: var(--nex-primary-text);
            --bs-list-group-bg: var(--nex-surface);
            --bs-list-group-color: var(--nex-text);
            --bs-list-group-border-color: var(--nex-border);
            --bs-list-group-action-hover-bg: var(--nex-raised);
            --bs-list-group-action-active-bg: var(--nex-raised);
            --bs-light: var(--nex-raised);
            --bs-light-rgb: 46, 32, 21;
            --bs-dark: var(--nex-raised);
            --bs-dark-rgb: 46, 32, 21;
            --bs-primary: var(--nex-primary-bg);
            --bs-primary-rgb: 201, 162, 75;
            --bs-link-color: var(--nex-link);
            --bs-link-hover-color: var(--nex-link-hover);
            --bs-link-color-rgb: 217, 176, 112;
            --bs-link-hover-color-rgb: 232, 200, 136;
            --bs-focus-ring-color: rgba(201, 162, 75, .45);
            --bs-nav-pills-link-active-bg: var(--nex-primary-bg);
            --bs-nav-pills-link-active-color: var(--nex-primary-text);
            --bs-progress-bar-bg: var(--nex-primary-bg);
            --bs-table-color: var(--nex-text);
            --bs-table-border-color: var(--nex-border);
            --bs-table-striped-bg: var(--nex-zebra);
            --bs-table-striped-color: var(--nex-text);
            --bs-table-hover-bg: var(--nex-zebra);
            --bs-table-hover-color: var(--nex-text);
            --bs-table-active-bg: var(--nex-zebra);
            --bs-table-active-color: var(--nex-text);
            --bs-success-bg-subtle: var(--nex-success-bg);
            --bs-success-text: var(--nex-success-text);
            --bs-success-border-subtle: var(--nex-success-border);
            --bs-warning-bg-subtle: var(--nex-warning-subtle-bg);
            --bs-warning-text: var(--nex-warning-subtle-text);
            --bs-warning-border-subtle: var(--nex-warning-subtle-border);
            --bs-danger-bg-subtle: var(--nex-danger-bg);
            --bs-danger-text: var(--nex-danger-text);
            --bs-danger-border-subtle: var(--nex-danger-border);
        }

        body { background: var(--nex-bg); color: var(--nex-text); }
        .main-content { margin-left: 240px; padding: 1.5rem; }
        .card { border: 1px solid var(--nex-border); border-radius: .75rem; }
        .text-gold { color: var(--nex-gold); }
        a { text-decoration: none; }
        a:hover { text-decoration: underline; }

        .sidebar {
            width: 240px; height: 100vh;
            background: linear-gradient(180deg, var(--nex-sidebar-bg) 0%, var(--nex-sidebar-bg-end) 100%);
            position: fixed; top: 0; left: 0;
            display: flex; flex-direction: column;
        }
        .sidebar > .nav { flex: 1 1 auto; overflow-y: auto; min-height: 0; }
        .sidebar > a { color: var(--nex-sidebar-text) !important; }
        .sidebar .nav-link { color: var(--nex-sidebar-muted); border-radius: .5rem; transition: background .15s, color .15s; }
        .sidebar .nav-link:hover { color: var(--nex-sidebar-text); background: rgba(243, 231, 215, .08); }
        .sidebar .nav-link.active { color: var(--nex-sidebar-active-text); background: var(--nex-sidebar-active-bg); font-weight: 600; }
        .sidebar hr { border-color: var(--nex-sidebar-border); opacity: 1; }
        .sidebar .text-white-50 { color: var(--nex-sidebar-muted) !important; }
        .sidebar .btn-outline-light { color: var(--nex-sidebar-text); border-color: var(--nex-sidebar-border); }
        .sidebar .btn-outline-light:hover { background: var(--nex-sidebar-active-bg); border-color: var(--nex-sidebar-active-bg); color: var(--nex-sidebar-active-text); }

        .btn { font-weight: 600; }
        .btn-primary {
            --bs-btn-color: var(--nex-primary-text);
            --bs-btn-bg: var(--nex-primary-bg);
            --bs-btn-border-color: var(--nex-primary-bg);
            --bs-btn-hover-color: var(--nex-primary-text);
            --bs-btn-hover-bg: var(--nex-primary-hover);
            --bs-btn-hover-border-color: var(--nex-primary-hover);
            --bs-btn-active-color: var(--nex-primary-text);
            --bs-btn-active-bg: var(--nex-primary-hover);
            --bs-btn-active-border-color: var(--nex-primary-hover);
            --bs-btn-disabled-color: var(--nex-muted);
            --bs-btn-disabled-bg: var(--nex-border);
            --bs-btn-disabled-border-color: var(--nex-border);
        }
        .btn-outline-primary {
            --bs-btn-color: var(--nex-primary-bg);
            --bs-btn-border-color: var(--nex-primary-bg);
            --bs-btn-hover-color: var(--nex-primary-text);
            --bs-btn-hover-bg: var(--nex-primary-bg);
            --bs-btn-hover-border-color: var(--nex-primary-bg);
            --bs-btn-active-color: var(--nex-primary-text);
            --bs-btn-active-bg: var(--nex-primary-bg);
            --bs-btn-active-border-color: var(--nex-primary-bg);
        }
        .btn-light {
            --bs-btn-color: var(--nex-text);
            --bs-btn-bg: var(--nex-raised);
            --bs-btn-border-color: var(--nex-border);
            --bs-btn-hover-color: var(--nex-text);
            --bs-btn-hover-bg: var(--nex-zebra);
            --bs-btn-hover-border-color: var(--nex-border);
            --bs-btn-active-color: var(--nex-text);
            --bs-btn-active-bg: var(--nex-zebra);
            --bs-btn-active-border-color: var(--nex-border);
        }
        .btn-spa { background: var(--nex-primary-bg); color: var(--nex-primary-text); border: 0; transition: background .2s; }
        .btn-spa:hover, .btn-spa:focus { background: var(--nex-primary-hover); color: var(--nex-primary-text); }

        .table { --bs-table-color: var(--nex-text); --bs-table-border-color: var(--nex-border); }
        .table thead th { background: var(--nex-table-header-bg); color: var(--nex-table-header-text); font-weight: 600; }
        .table-striped > tbody > tr:nth-of-type(odd) > * { --bs-table-bg: var(--nex-zebra); color: var(--nex-text); }
        .table-hover > tbody > tr:hover > * { --bs-table-bg: var(--nex-zebra); color: var(--nex-text); }

        .text-bg-warning { background-color: var(--nex-warning-bg) !important; color: var(--nex-warning-text) !important; }
        .text-warning { color: var(--nex-warning-strong) !important; }
        .text-bg-success { background-color: var(--nex-success-bg) !important; color: var(--nex-success-text) !important; }
        .text-bg-danger { background-color: var(--nex-danger-bg) !important; color: var(--nex-danger-text) !important; }
        .text-bg-light { background-color: var(--nex-table-header-bg) !important; color: var(--nex-table-header-text) !important; }
        .text-bg-secondary { background-color: var(--nex-sidebar-bg) !important; color: var(--nex-sidebar-text) !important; }
        .text-bg-primary { background-color: var(--nex-primary-bg) !important; color: var(--nex-primary-text) !important; }
        .text-success { color: var(--nex-success-text) !important; }
        .text-danger { color: var(--nex-danger-text) !important; }

        .alert-success { --bs-alert-bg: var(--nex-success-bg); --bs-alert-color: var(--nex-success-text); --bs-alert-border-color: var(--nex-success-border); }
        .alert-warning { --bs-alert-bg: var(--nex-warning-subtle-bg); --bs-alert-color: var(--nex-warning-subtle-text); --bs-alert-border-color: var(--nex-warning-subtle-border); }
        .alert-danger { --bs-alert-bg: var(--nex-danger-bg); --bs-alert-color: var(--nex-danger-text); --bs-alert-border-color: var(--nex-danger-border); }
        .alert-secondary { --bs-alert-bg: var(--nex-table-header-bg); --bs-alert-color: var(--nex-text); --bs-alert-border-color: var(--nex-border); }

        .form-control, .form-select { background-color: var(--nex-raised); color: var(--nex-text); border-color: var(--nex-border); }
        .form-control:focus, .form-select:focus { border-color: var(--nex-accent); box-shadow: 0 0 0 .25rem rgba(181, 137, 90, .25); }
        .form-check-input:checked { background-color: var(--nex-accent); border-color: var(--nex-accent); }
        .input-group-text { background-color: var(--nex-zebra); color: var(--nex-text); border-color: var(--nex-border); }

        @media (max-width: 991px) {
            .sidebar { position: static; width: 100%; height: auto; }
            .sidebar > .nav { flex: 0 0 auto; overflow-y: visible; }
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
            NEXFLIO <small class="d-block fs-6 fw-normal opacity-75">Perfect Nails Staff</small>
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
