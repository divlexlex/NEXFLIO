<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Transaction Record') · Perfect Nails</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        /* Dedicated minimal receipt layout: centered card on the brand
           background — no public nav, no admin sidebar. */
        body { background: #FAF5EE; color: #2B1A12; }
        .nx-receipt-wrap { max-width: 560px; margin: 0 auto; padding: 2.5rem 1rem; }
        .nx-receipt-card {
            background: #FFFFFF; border: 1px solid #E4D5C3; border-radius: .75rem;
            box-shadow: 0 2px 8px rgba(46, 33, 23, .08); padding: 2rem;
        }
        .nx-receipt-brand { font-family: Georgia, 'Times New Roman', serif; font-size: 1.75rem; }
        .nx-receipt-sub { letter-spacing: .35em; font-size: .7rem; color: #71604F; }
        .nx-receipt-row {
            display: flex; justify-content: space-between; gap: 1rem;
            padding: .5rem 0; border-bottom: 1px solid #E4D5C3; font-size: .95rem;
        }
        .nx-receipt-row:last-child { border-bottom: 0; }
        .nx-receipt-label { color: #71604F; }
        .btn-spa { background: #A97C50; color: #FFFFFF; border: 0; font-weight: 600; }
        .btn-spa:hover, .btn-spa:focus { background: #8F653B; color: #FFFFFF; }
        @media print {
            body { background: #fff; }
            .nx-receipt-wrap { padding: 0; max-width: 100%; }
            .nx-receipt-card { border: 0; box-shadow: none; border-radius: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <main class="nx-receipt-wrap">
        @yield('content')
    </main>
</body>
</html>
