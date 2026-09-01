@extends('layouts.storefront')

@section('content')
<section class="section">
    <div class="container split">
        <div>
            <h1>Checkout</h1>
            <form method="post" action="{{ route('checkout.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="field"><label for="customer_name">Name</label><input id="customer_name" name="customer_name" autocomplete="name" value="{{ old('customer_name') }}" required></div>
                    <div class="field"><label for="customer_phone">Phone</label><input id="customer_phone" name="customer_phone" type="tel" autocomplete="tel" value="{{ old('customer_phone') }}" required></div>
                    <div class="field"><label for="customer_email">Email</label><input id="customer_email" name="customer_email" type="email" autocomplete="email" value="{{ old('customer_email') }}"></div>
                </div>
                <div class="field"><label for="delivery_address">Delivery address</label><textarea id="delivery_address" name="delivery_address" autocomplete="street-address" required>{{ old('delivery_address') }}</textarea></div>
                <div class="field"><label for="customer_notes">Notes</label><textarea id="customer_notes" name="customer_notes">{{ old('customer_notes') }}</textarea></div>
                <button class="primary" type="submit">Place COD Order</button>
            </form>
        </div>
        <aside class="card"><div class="card-body">
            <h2>Order Summary</h2>
            @foreach ($cart['items'] as $item)
                <p>{{ $item['quantity'] }} x {{ $item['variant']->product->sku }} {{ $item['variant']->size }} - LKR {{ number_format($item['line_total'], 2) }}<br><span class="muted">2 matching pillow cases included</span></p>
            @endforeach
            <h3>Subtotal: LKR {{ number_format($cart['subtotal'], 2) }}</h3>
            <p class="muted">Delivery fee is confirmed by admin.</p>
        </div></aside>
    </div>
</section>
@endsection
