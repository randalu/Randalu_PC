@php
    $variant = $product->activeVariants->first();
    $message = urlencode("Hello Priyanthi Multi Stores, I'm interested in {$product->sku} - {$product->name}.");
@endphp
<article class="card">
    <a class="product-img" href="{{ route('products.show', $product) }}">
        <img src="{{ asset($product->image_path ?: 'images/logo.webp') }}" alt="{{ $product->sku }} {{ $product->name }}">
    </a>
    <div class="card-body">
        <span class="sku">{{ $product->sku }}</span>
        <h3><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h3>
        <p class="muted">{{ $product->seo_description }}</p>
        <p class="included-note small">Includes 2 matching pillow cases free.</p>
        <p class="muted">{{ $product->category->name }}</p>
        <div class="actions">
            <a class="btn primary" href="{{ route('products.show', $product) }}">Order Online</a>
            <a class="btn green" href="https://wa.me/{{ $settings['whatsapp_number'] ?? '94776474542' }}?text={{ $message }}">WhatsApp</a>
        </div>
        @if ($variant)
            <p class="muted">From {{ $settings['currency'] ?? 'LKR' }} {{ number_format((float) $product->activeVariants->min('price'), 2) }}</p>
        @endif
    </div>
</article>
