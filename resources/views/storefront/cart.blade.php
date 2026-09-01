@extends('layouts.storefront')

@section('content')
<section class="section">
    <div class="container">
        <h1>Your Cart</h1>
        @if ($cart['items']->isEmpty())
            <p class="muted">Your cart is empty.</p>
            <a class="btn primary" href="{{ route('home') }}">Browse products</a>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Product</th><th>Variant</th><th>Qty</th><th>Total</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($cart['items'] as $item)
                        <tr>
                            <td>{{ $item['variant']->product->sku }} - {{ $item['variant']->product->name }}</td>
                            <td>{{ $item['variant']->size }}<br><span class="muted">2 matching pillow cases included</span></td>
                            <td>
                                <form method="post" action="{{ route('cart.update', $item['variant']) }}">@csrf @method('PATCH')
                                    <input name="quantity" type="number" min="1" value="{{ $item['quantity'] }}" style="width:80px">
                                    <button type="submit">Update</button>
                                </form>
                            </td>
                            <td>LKR {{ number_format($item['line_total'], 2) }}</td>
                            <td><form method="post" action="{{ route('cart.remove', $item['variant']) }}">@csrf @method('DELETE')<button class="danger" type="submit">Remove</button></form></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <h2>Subtotal: LKR {{ number_format($cart['subtotal'], 2) }}</h2>
            <a class="btn primary" href="{{ route('checkout.show') }}">Checkout</a>
        @endif
    </div>
</section>
@endsection
