@extends('layouts.storefront')

@section('title', $product->sku.' - '.$product->name.' | Randalu PC')
@section('description', $product->seo_description)

@section('content')
@php $message = urlencode("Hello Randalu PC, I'm interested in {$product->sku} - {$product->name}. Please share availability and pricing."); @endphp
<section class="section">
    <div class="container split">
        <div class="hero-image"><img src="{{ asset($product->image_path ?: 'images/product-placeholder.png') }}" alt="{{ $product->sku }} {{ $product->name }}"></div>
        <div>
            <span class="sku">{{ $product->sku }}</span>
            <h1>{{ $product->name }}</h1>
            <p class="muted">{{ $product->category->name }}</p>
            <p>{{ $product->seo_description }}</p>
            <p class="included-note">Genuine hardware with warranty support.</p>
            <form method="post" action="{{ route('cart.add') }}">
                @csrf
                <div class="field">
                    <label for="variant_id">Select variant</label>
                    <select id="variant_id" name="variant_id" required>
                        <option value="" selected disabled>Select variant</option>
                        @foreach ($product->activeVariants as $variant)
                            <option value="{{ $variant->id }}">{{ $variant->size }} | Stock {{ $variant->stock_quantity }} | LKR {{ number_format((float) $variant->price, 2) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="quantity">Quantity</label>
                    <input id="quantity" name="quantity" type="number" min="1" value="1" required>
                </div>
                <div class="actions">
                    <button class="primary" type="submit">Add to Cart</button>
                    <a class="btn green" href="https://wa.me/{{ $settings['whatsapp_number'] ?? '94776474542' }}?text={{ $message }}">WhatsApp Inquiry</a>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection
