@extends('layouts.storefront')

@section('content')
<section class="hero">
    <div class="container hero-grid">
        <div>
            <h1>Shop computer hardware at Randalu PC</h1>
            <p>Browse our catalog of laptops, components, and peripherals, send a WhatsApp inquiry, or place an online order for admin confirmation and delivery.</p>
            <div class="actions">
                <a class="btn primary" href="#products">Shop Hardware</a>
                <a class="btn" href="#contact">Contact Store</a>
                <a class="btn" href="{{ route('cart.show') }}">View Cart</a>
            </div>
        </div>
        <div class="hero-image"><img src="{{ asset('images/hero-hardware.png') }}" alt="Randalu PC computer hardware"></div>
    </div>
</section>
<section class="section" id="products">
    <div class="container">
        <div class="toolbar">
            <form method="get"><input class="search" name="s" value="{{ $search }}" placeholder="Search by SKU, name, or collection"></form>
            <div class="filters">
                <a class="filter" href="{{ route('home') }}">All</a>
                @foreach ($categories as $category)
                    <a class="filter" href="{{ route('collections.show', $category) }}">{{ $category->name }}</a>
                @endforeach
            </div>
        </div>
        <div class="grid">
            @foreach ($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
</section>
<section class="contact-section" id="contact">
    <div class="container contact-grid">
        <div class="contact-box">
            <span class="sku">Visit or call</span>
            <h2>{{ $settings['store_name'] ?? 'Randalu PC' }}</h2>
            <p class="muted">Call or WhatsApp to confirm stock availability, delivery options, and bulk orders.</p>
            <div class="contact-list">
                <a href="tel:{{ $settings['store_phone'] ?? '+94776474542' }}">{{ $settings['store_phone'] ?? '+94776474542' }}</a>
                <a href="https://wa.me/{{ $settings['whatsapp_number'] ?? '94776474542' }}">WhatsApp {{ $settings['whatsapp_number'] ?? '94776474542' }}</a>
                <span>{{ $settings['store_address'] ?? 'Randalu PC, Sri Lanka' }}</span>
            </div>
        </div>
        @if (! empty($settings['google_maps_embed_url']))
            <div class="map-box">
                <iframe src="{{ $settings['google_maps_embed_url'] }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Randalu PC Google Map location"></iframe>
            </div>
        @endif
    </div>
</section>
@endsection
