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
        /* ============ NEXFLIO design tokens (single source of truth) ============
           Every admin/manager surface is driven by these --nex-* variables.
           Light tokens live on :root; dark tokens override on
           html[data-bs-theme="dark"] (Bootstrap theme attribute in <head>). */
        :root {
            /* page / surfaces */
            --nex-bg: #FAF5EE;
            --nex-surface: #FFFFFF;
            --nex-raised: #FFFFFF;
            /* sidebar — always a dark brown with cream text, gold active */
            --nex-sidebar-bg: #2E1B10;
            --nex-sidebar-bg-end: #20140B;
            --nex-sidebar-text: #F3E7D7;
            --nex-sidebar-muted: rgba(243, 231, 215, .74);
            --nex-sidebar-border: rgba(243, 231, 215, .16);
            --nex-sidebar-active-bg: #C9A24B;
            --nex-sidebar-active-text: #2E1B10;
            /* text */
            --nex-text: #2B1A12;
            --nex-muted: #71604F;
            --nex-border: #E4D5C3;
            --nex-table-header-bg: #F3E9DC;
            --nex-table-header-text: #2B1A12;
            --nex-zebra: #FCF8F2;
            /* brand / buttons / links */
            --nex-primary-bg: #A97C50;
            --nex-primary-text: #FFFFFF;
            --nex-primary-hover: #8F653B;
            --nex-accent: #B5895A;
            --nex-gold: #C9A24B;
            --nex-link: #8A5A2B;
            --nex-link-hover: #6E4520;
            /* status — success/danger are tint bg + strong text (AA);
               warning badge is amber bg + near-black text in BOTH themes */
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

        /* ------- Bootstrap 5.3 core variable remap ------- */
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

        /* ------- base ------- */
        body { background: var(--nex-bg); color: var(--nex-text); }
        .main-content { margin-left: 240px; padding: 1.5rem; }
        .card { border: 1px solid var(--nex-border); border-radius: .75rem; }
        .text-gold { color: var(--nex-gold); }
        a { text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ------- sidebar ------- */
        .sidebar {
            width: 240px; height: 100vh;
            background: linear-gradient(180deg, var(--nex-sidebar-bg) 0%, var(--nex-sidebar-bg-end) 100%);
            position: fixed; top: 0; left: 0;
            display: flex; flex-direction: column;
        }
        /* ------- sidebar: brand + nav only. The nav list takes the
           remaining height and scrolls VERTICALLY only — never
           horizontally (badges stay inline, links can't force overflow). */
        .sidebar > .nav {
            flex: 1 1 auto; overflow-y: auto; overflow-x: hidden; min-height: 0;
            flex-wrap: nowrap; overscroll-behavior: contain;
            scrollbar-width: thin; scrollbar-color: rgba(201, 162, 75, .55) transparent;
        }
        .sidebar > .nav::-webkit-scrollbar { width: 6px; }
        .sidebar > .nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar > .nav::-webkit-scrollbar-thumb { background: rgba(201, 162, 75, .45); border-radius: 3px; }
        .sidebar > a { color: var(--nex-sidebar-text) !important; }
        .sidebar .nav-item { min-width: 0; }
        .sidebar .nav-link { color: var(--nex-sidebar-muted); border-radius: .5rem; transition: background .15s, color .15s; min-width: 0; }
        .sidebar .nav-link:hover { color: var(--nex-sidebar-text); background: rgba(243, 231, 215, .08); }
        .sidebar .nav-link.active { color: var(--nex-sidebar-active-text); background: var(--nex-sidebar-active-bg); font-weight: 600; }
        .sidebar hr { border-color: var(--nex-sidebar-border); opacity: 1; }
        .sidebar .text-white-50 { color: var(--nex-sidebar-muted) !important; }
        .sidebar .btn-outline-light { color: var(--nex-sidebar-text); border-color: var(--nex-sidebar-border); }
        .sidebar .btn-outline-light:hover { background: var(--nex-sidebar-active-bg); border-color: var(--nex-sidebar-active-bg); color: var(--nex-sidebar-active-text); }
        .nx-admin-sidebar-backdrop { display: none; }

        /* ------- top header bar (account/theme/logout live here now) ------- */
        .nx-topbar {
            background: var(--nex-surface); color: var(--nex-text);
            border: 1px solid var(--nex-border); border-radius: .75rem;
        }
        .nx-avatar {
            width: 32px; height: 32px; flex: 0 0 32px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%; background: var(--nex-primary-bg); color: var(--nex-primary-text);
            font-size: .8rem; font-weight: 700;
        }

        /* ------- smooth Bootstrap dropdowns ------- */
        .dropdown-menu {
            display: block;
            opacity: 0; visibility: hidden; transform: translateY(6px); pointer-events: none;
            transition: opacity .2s ease, transform .2s ease, visibility 0s linear .2s;
        }
        .dropdown-menu.show {
            opacity: 1; visibility: visible; transform: translateY(0); pointer-events: auto;
            transition-delay: 0s, 0s, 0s;
        }
        .dropdown-toggle::after { transition: transform .2s ease; }
        .dropdown-toggle.show::after { transform: rotate(180deg); }

        /* ------- buttons ------- */
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

        /* ------- tables ------- */
        .table { --bs-table-color: var(--nex-text); --bs-table-border-color: var(--nex-border); }
        .table thead th { background: var(--nex-table-header-bg); color: var(--nex-table-header-text); font-weight: 600; }
        .table-striped > tbody > tr:nth-of-type(odd) > * { --bs-table-bg: var(--nex-zebra); color: var(--nex-text); }
        .table-hover > tbody > tr:hover > * { --bs-table-bg: var(--nex-zebra); color: var(--nex-text); }

        /* ------- status chips / badges (AA-safe in both themes) ------- */
        .text-bg-warning { background-color: var(--nex-warning-bg) !important; color: var(--nex-warning-text) !important; }
        .text-warning { color: var(--nex-warning-strong) !important; }
        .text-bg-success { background-color: var(--nex-success-bg) !important; color: var(--nex-success-text) !important; }
        .text-bg-danger { background-color: var(--nex-danger-bg) !important; color: var(--nex-danger-text) !important; }
        .text-bg-light { background-color: var(--nex-table-header-bg) !important; color: var(--nex-table-header-text) !important; }
        .text-bg-secondary { background-color: var(--nex-sidebar-bg) !important; color: var(--nex-sidebar-text) !important; }
        .text-bg-primary { background-color: var(--nex-primary-bg) !important; color: var(--nex-primary-text) !important; }
        .text-success { color: var(--nex-success-text) !important; }
        .text-danger { color: var(--nex-danger-text) !important; }

        /* ------- alerts ------- */
        .alert-success { --bs-alert-bg: var(--nex-success-bg); --bs-alert-color: var(--nex-success-text); --bs-alert-border-color: var(--nex-success-border); }
        .alert-warning { --bs-alert-bg: var(--nex-warning-subtle-bg); --bs-alert-color: var(--nex-warning-subtle-text); --bs-alert-border-color: var(--nex-warning-subtle-border); }
        .alert-danger { --bs-alert-bg: var(--nex-danger-bg); --bs-alert-color: var(--nex-danger-text); --bs-alert-border-color: var(--nex-danger-border); }
        .alert-secondary { --bs-alert-bg: var(--nex-table-header-bg); --bs-alert-color: var(--nex-text); --bs-alert-border-color: var(--nex-border); }

        /* ------- forms ------- */
        .form-control, .form-select { background-color: var(--nex-raised); color: var(--nex-text); border-color: var(--nex-border); }
        .form-control:focus, .form-select:focus { border-color: var(--nex-accent); box-shadow: 0 0 0 .25rem rgba(181, 137, 90, .25); }
        .form-check-input:checked { background-color: var(--nex-accent); border-color: var(--nex-accent); }
        .input-group-text { background-color: var(--nex-zebra); color: var(--nex-text); border-color: var(--nex-border); }

        /* ------- pagination ------- */
        .page-link {
            --bs-pagination-bg: var(--nex-surface);
            --bs-pagination-color: var(--nex-link);
            --bs-pagination-border-color: var(--nex-border);
            --bs-pagination-hover-bg: var(--nex-zebra);
            --bs-pagination-hover-color: var(--nex-link-hover);
            --bs-pagination-focus-bg: var(--nex-zebra);
            --bs-pagination-focus-color: var(--nex-link);
            --bs-pagination-active-bg: var(--nex-primary-bg);
            --bs-pagination-active-border-color: var(--nex-primary-bg);
            --bs-pagination-active-color: var(--nex-primary-text);
            --bs-pagination-disabled-bg: var(--nex-bg);
            --bs-pagination-disabled-color: var(--nex-muted);
            --bs-pagination-disabled-border-color: var(--nex-border);
        }

        @media (max-width: 991.98px) {
            .sidebar { position: fixed; top: 0; left: 0; width: 50vw; min-width: 240px; max-width: 320px; height: 100dvh; z-index: 1090; transform: translateX(-105%); visibility: hidden; transition: transform .38s cubic-bezier(.22,.8,.24,1), visibility 0s linear .38s; box-shadow: 0 0 44px rgba(0,0,0,.45); }
            .sidebar.nx-admin-open { transform: translateX(0); visibility: visible; transition-delay: 0s, 0s; }
            .sidebar > .nav { flex: 1 1 auto; overflow-y: auto; overflow-x: hidden; }
            .sidebar > a:first-of-type { padding-right: 3.25rem; }
            .nx-admin-sidebar-close { position: absolute; top: .9rem; right: .9rem; width: 38px; height: 38px; border-radius: 50%; border: 1px solid var(--nex-sidebar-border); background: transparent; color: var(--nex-sidebar-text); display: inline-flex; align-items: center; justify-content: center; }
            .nx-admin-sidebar-backdrop { display: block; position: fixed; inset: 0; z-index: 1085; border: 0; padding: 0; background: rgba(20,12,7,.55); opacity: 0; visibility: hidden; pointer-events: none; transition: opacity .3s ease, visibility 0s linear .3s; }
            .nx-admin-sidebar-backdrop.nx-admin-open { opacity: 1; visibility: visible; pointer-events: auto; transition-delay: 0s, 0s; }
            .nx-admin-sidebar-backdrop[hidden] { display: none; }
            body.nx-admin-drawer-open { overflow: hidden; }
            .main-content { margin-left: 0; padding: 1rem; }
            .nx-topbar { position: sticky; top: .75rem; z-index: 1020; flex-wrap: nowrap; }
            .nx-admin-menu-btn { width: 40px; height: 40px; flex: 0 0 40px; border-radius: .5rem; border: 1px solid var(--nex-border); background: var(--nex-raised); color: var(--nex-text); display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; }
            #nxClock { flex: 1 1 auto; min-width: 0; font-size: .78rem; line-height: 1.3; }
            .nx-topbar .dropdown { flex: 0 0 auto; }
            .nx-topbar .dropdown-toggle { max-width: 11rem; }
            .nx-topbar .dropdown-toggle .text-start { min-width: 0; }
            .nx-topbar .dropdown-toggle .text-start > span { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        }

        @media (max-width: 575.98px) {
            .nx-topbar .dropdown-toggle .text-start > span:last-child { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            .sidebar, .nx-admin-sidebar-backdrop, .dropdown-menu, .dropdown-toggle::after { transition: none; }
        }

        /* Manual refresh button, Design C (dark rounded square, gold icon,
           icon only) — see partials/refresh-button + public/js/nx-refresh.js.
           Lives in the topbar beside the user dropdown; spins while loading. */
        .nx-refresh-btn { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 12px; border: 1px solid #3A2F27; background: #2B211B; color: #D4A94E; font-size: 20px; line-height: 1; cursor: pointer; flex-shrink: 0; }
        .nx-refresh-btn:disabled { opacity: .7; cursor: wait; }
        .nx-refresh-btn.nx-spinning [data-nx-refresh-icon] { display: inline-block; animation: nx-spin 1s cubic-bezier(.65, 0, .35, 1) infinite; }
        @keyframes nx-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
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
    <aside class="sidebar p-3" id="adminSidebar">
        <a href="{{ route('admin.dashboard') }}" class="d-block text-white text-decoration-none fs-5 fw-bold mb-4 px-2">
            {{-- Shared by Owner (role 1) and Manager (role 2) — label reflects
                 whoever's actually signed in instead of always saying "Admin". --}}
            NEXFLIO <small class="d-block fs-6 fw-normal opacity-75">Perfect Nails {{ auth()->user()->role_id === \App\Models\User::ROLE_SUPER_ADMIN ? 'Admin' : 'Manager' }}</small>
        </a>
        <button type="button" id="adminSidebarClose" class="nx-admin-sidebar-close d-lg-none" aria-label="Close admin menu"><i class="bi bi-x-lg"></i></button>
        <ul class="nav flex-column gap-1">
            @php
                $pendingAppointments = \App\Models\Appointment::where('status', 'unverified')->count();
                $pendingPayments = \App\Models\Payment::where('status', 'pending')->count();
                $pendingChangeRequests = \App\Models\AppointmentChangeRequest::where('status', 'pending')->count();
                $pendingLeaves = \App\Models\LeaveRequest::where('status', 'pending')->count();
            @endphp
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center {{ request()->routeIs('admin.appointments') ? 'active' : '' }}" href="{{ route('admin.appointments') }}">
                    <i class="bi bi-calendar-event me-2"></i>Appointments
                    @if($pendingAppointments)
                        <span class="badge text-bg-warning ms-auto">{{ $pendingAppointments }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.master-schedule') ? 'active' : '' }}" href="{{ route('admin.master-schedule') }}">
                    <i class="bi bi-calendar3 me-2"></i>Master Schedule
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center {{ request()->routeIs('admin.appointment-requests') ? 'active' : '' }}" href="{{ route('admin.appointment-requests') }}">
                    <i class="bi bi-arrow-left-right me-2"></i>Change Requests
                    @if($pendingChangeRequests)
                        <span class="badge text-bg-warning ms-auto">{{ $pendingChangeRequests }}</span>
                    @endif
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center {{ request()->routeIs('admin.payments') ? 'active' : '' }}" href="{{ route('admin.payments') }}">
                    <i class="bi bi-cash-coin me-2"></i>Payments
                    @if($pendingPayments)
                        <span class="badge text-bg-warning ms-auto">{{ $pendingPayments }}</span>
                    @endif
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
                <a class="nav-link {{ request()->routeIs('admin.clients*') ? 'active' : '' }}" href="{{ route('admin.clients') }}">
                    <i class="bi bi-person-lines-fill me-2"></i>Clients
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.reviews*') ? 'active' : '' }}" href="{{ route('admin.reviews') }}">
                    <i class="bi bi-chat-left-text me-2"></i>Feedback
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center {{ request()->routeIs('admin.leaves') ? 'active' : '' }}" href="{{ route('admin.leaves') }}">
                    <i class="bi bi-calendar-x me-2"></i>Leave Requests
                    @if($pendingLeaves)
                        <span class="badge text-bg-warning ms-auto">{{ $pendingLeaves }}</span>
                    @endif
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
                <a class="nav-link {{ request()->routeIs('admin.promos*') ? 'active' : '' }}" href="{{ route('admin.promos') }}">
                    <i class="bi bi-tags me-2"></i>Promotions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.availability*') ? 'active' : '' }}" href="{{ route('admin.availability') }}">
                    <i class="bi bi-clock-history me-2"></i>Availability
                </a>
            </li>
            @if((int) auth()->user()->role_id === \App\Models\User::ROLE_SUPER_ADMIN)
                <li class="mt-3 px-2 text-uppercase small text-white-50">Owner</li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.audit-logs') ? 'active' : '' }}" href="{{ route('admin.audit-logs') }}">
                        <i class="bi bi-shield-lock me-2"></i>Activity Log
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.reports.financial') ? 'active' : '' }}" href="{{ route('admin.reports.financial') }}">
                        <i class="bi bi-graph-up-arrow me-2"></i>Financial Reports
                    </a>
                </li>
            @endif
        </ul>
    </aside>
    <button type="button" id="adminSidebarBackdrop" class="nx-admin-sidebar-backdrop" aria-label="Close admin menu" hidden></button>

    <main class="main-content flex-grow-1">
        @php
            $topbarUser = auth()->user();
            $topbarInitials = strtoupper(implode('', array_map(
                fn ($p) => mb_substr($p, 0, 1),
                array_slice(preg_split('/\s+/', trim($topbarUser->name ?? '')), 0, 2)
            ))) ?: '?';
            $topbarRole = (int) $topbarUser->role_id === \App\Models\User::ROLE_SUPER_ADMIN ? 'Owner' : 'Manager';
        @endphp
        <header class="nx-topbar d-flex align-items-center justify-content-between gap-2 mb-3 px-3 py-2">
            <button type="button" id="adminSidebarToggle" class="nx-admin-menu-btn d-lg-none" aria-controls="adminSidebar" aria-expanded="false" aria-label="Open admin menu"><i class="bi bi-list"></i></button>
            <span id="nxClock" class="small fw-semibold">{{ now()->format('l, F j, Y · g:i:s A') }}</span>
            <div class="dropdown d-flex align-items-center gap-2">
                @include('partials.refresh-button')
                <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-2 dropdown-toggle"
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="nx-avatar">{{ $topbarInitials }}</span>
                    <span class="text-start">
                        <span class="d-block fw-semibold">{{ $topbarUser->name }}</span>
                        <span class="d-block small text-muted">{{ $topbarRole }}</span>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <h6 class="dropdown-header">{{ $topbarUser->name }}<span class="d-block fw-normal">{{ $topbarUser->email }}</span></h6>
                    </li>
                    <li>
                        <button type="button" id="themeToggle" class="dropdown-item">
                            <i class="bi bi-moon-stars me-1"></i><span id="themeToggleLabel">Dark mode</span>
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown-item"><i class="bi bi-box-arrow-right me-1"></i>Log out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>
        <script>
            // Live topbar clock (12-hour, never 24-hour): server-rendered above,
            // then ticking client-side every second.
            (function () {
                const el = document.getElementById('nxClock');
                if (!el) return;
                const tick = () => {
                    const now = new Date();
                    const date = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                    const time = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
                    el.textContent = date + ' · ' + time;
                };
                tick();
                setInterval(tick, 1000);
            })();
        </script>
        @include('partials.flash-alerts')
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" data-nx-flash>
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
    // Admin sidebar drawer (mobile only).
    (function () {
        const sidebar = document.getElementById('adminSidebar');
        const toggle = document.getElementById('adminSidebarToggle');
        const closeBtn = document.getElementById('adminSidebarClose');
        const backdrop = document.getElementById('adminSidebarBackdrop');
        if (!sidebar || !toggle || !closeBtn || !backdrop) return;

        const openDrawer = () => {
            if (window.innerWidth >= 992) return;
            sidebar.classList.add('nx-admin-open');
            backdrop.hidden = false;
            backdrop.classList.add('nx-admin-open');
            document.body.classList.add('nx-admin-drawer-open');
            toggle.setAttribute('aria-expanded', 'true');
        };

        const closeDrawer = () => {
            sidebar.classList.remove('nx-admin-open');
            backdrop.classList.remove('nx-admin-open');
            backdrop.hidden = true;
            document.body.classList.remove('nx-admin-drawer-open');
            toggle.setAttribute('aria-expanded', 'false');
        };

        toggle.addEventListener('click', () => {
            if (sidebar.classList.contains('nx-admin-open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
        closeBtn.addEventListener('click', closeDrawer);
        backdrop.addEventListener('click', closeDrawer);
        sidebar.querySelectorAll('.nav-link').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) closeDrawer();
            });
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && sidebar.classList.contains('nx-admin-open')) {
                closeDrawer();
            }
        });
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 992) closeDrawer();
        });

        closeDrawer();
    })();
</script>
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
@include('admin.partials.flio')
{{-- Manual refresh script: no-ops on pages without the button + region. --}}
<script src="{{ asset('js/nx-refresh.js') }}" defer></script>
@stack('scripts')
</body>
</html>
