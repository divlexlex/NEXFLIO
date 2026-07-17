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
            --spa-cream: #F7ECE1;
            --spa-tan: #E8D5C4;
            --spa-gold: #9C7A54;
            --spa-espresso: #3D2817;
            --spa-blush: #E8B4B8;
        }
        body { font-family: 'Montserrat', sans-serif; background: var(--spa-cream); color: var(--spa-espresso); }
        h1, h2, h3, .brand-font { font-family: 'Playfair Display', serif; }
        .btn-spa { background: var(--spa-espresso); color: #fff; border: none; }
        .btn-spa:hover { background: var(--spa-gold); color: #fff; }
        .btn-outline-spa { border: 1px solid var(--spa-espresso); color: var(--spa-espresso); }
        .btn-outline-spa:hover { background: var(--spa-espresso); color: #fff; }
        .text-gold { color: var(--spa-gold); }
        .bg-espresso { background: var(--spa-espresso); }
        .card-spa { background: #fff; border: 1px solid var(--spa-tan); border-radius: 1rem; }
        .navbar-spa { background: var(--spa-cream); border-bottom: 1px solid var(--spa-tan); }
    </style>
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
                <li class="nav-item"><a class="nav-link" href="{{ route('landing') }}#about">About Us</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('landing') }}#contact">Contact</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('landing') }}#app">Get the App</a></li>
                <li class="nav-item">
                    <a class="btn btn-outline-spa btn-sm px-3" href="{{ route('login') }}">Management Login</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

@yield('content')

<footer class="bg-espresso text-white py-4 mt-5">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <span class="brand-font">Perfect Nails Wellness and Aesthetics</span>
        <small class="opacity-75">&copy; {{ date('Y') }} NEXFLIO Business Management System</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

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
