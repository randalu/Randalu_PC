<!doctype html>
<html lang="en-LK">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Computer Hardware & Parts in Sri Lanka | Randalu PC')</title>
    <meta name="description" content="@yield('description', 'Shop computer hardware and parts in Sri Lanka at Randalu PC.')">
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="site-header">
    <div class="container nav">
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ asset('images/logo.png') }}" alt="Randalu PC logo">
            <span>Randalu PC</span>
        </a>
        <nav class="nav-links">
            <a href="{{ route('home') }}">Shop</a>
            <a href="{{ route('orders.status') }}">Order status</a>
            <a href="{{ route('cart.show') }}">Cart ({{ $cart_count ?? 0 }})</a>
            @if ($customer)
                <a href="{{ route('customer.account') }}">Account</a>
                <form method="post" action="{{ route('customer.logout') }}" class="nav-logout-form">
                    @csrf
                    <button type="submit" class="nav-logout">Sign out</button>
                </form>
            @else
                <a href="{{ route('customer.login') }}">Sign in</a>
            @endif
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
            <strong>{{ $settings['store_name'] ?? 'Randalu PC' }}</strong>
            <span>Genuine computer hardware and parts in Sri Lanka.</span>
        </div>
        <div>
            <a href="tel:{{ $settings['store_phone'] ?? '+94776474542' }}">{{ $settings['store_phone'] ?? '+94776474542' }}</a>
            <a href="https://wa.me/{{ $settings['whatsapp_number'] ?? '94776474542' }}">WhatsApp</a>
        </div>
    </div>
</footer>
</body>
</html>
