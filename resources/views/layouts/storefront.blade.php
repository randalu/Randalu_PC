<!doctype html>
<html lang="en-LK">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Bedsheets & Pillowcases in Sri Lanka | Priyanthi Multi Stores')</title>
    <meta name="description" content="@yield('description', 'Order locally tailored bedsheet sets from Priyanthi Multi Stores in Sri Lanka.')">
    <link rel="icon" href="{{ asset('images/logo.webp') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="site-header">
    <div class="container nav">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ asset('images/logo.webp') }}" alt="Priyanthi Multi Stores logo">
            <span>Priyanthi Multi Stores</span>
        </a>
        <nav class="nav-links">
            <a href="{{ route('home') }}">Products</a>
            <a href="{{ route('orders.status') }}">Order status</a>
            <a href="{{ route('home') }}#contact">Contact</a>
            <a href="{{ route('cart.show') }}">Cart ({{ count(session('cart', [])) }})</a>
            <a class="btn green" href="https://wa.me/{{ $settings['whatsapp_number'] ?? '94776474542' }}">WhatsApp</a>
        </nav>
    </div>
</header>
<main>
    @if (session('status')) <div class="container notice">{{ session('status') }}</div> @endif
    @if ($errors->any()) <div class="container errors">{{ $errors->first() }}</div> @endif
    @yield('content')
</main>
<footer class="footer">
    <div class="container footer-grid">
        <div>
            <strong>{{ $settings['store_name'] ?? 'Priyanthi Multi Stores' }}</strong>
            <span>Bedsheet sets with 2 matching pillowcases included.</span>
        </div>
        <div>
            <a href="tel:{{ $settings['store_phone'] ?? '+94776474542' }}">{{ $settings['store_phone'] ?? '+94776474542' }}</a>
            <a href="https://wa.me/{{ $settings['whatsapp_number'] ?? '94776474542' }}">WhatsApp</a>
        </div>
    </div>
</footer>
</body>
</html>
