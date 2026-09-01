@extends('layouts.storefront')

@section('content')
<section class="section">
    <div class="container">
        <h1>Order received</h1>
        <p>Your order number is <strong>{{ $order->order_number }}</strong>.</p>
        <p class="muted">We will confirm availability, delivery fee, and dispatch details. Each bedsheet set includes 2 matching pillow cases free.</p>
        <a class="btn primary" href="{{ route('home') }}">Continue shopping</a>
    </div>
</section>
@endsection
