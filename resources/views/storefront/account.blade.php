@extends('layouts.storefront')

@section('title', 'My Account | Randalu PC')

@section('content')
<section class="section">
    <div class="container split">
        <div>
            <div class="shop-head">
                <div>
                    <span class="sku">Account</span>
                    <h1>My Account</h1>
                    <p class="muted">Signed in as {{ $customer->phone }}</p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2>Profile</h2>
                    <form method="post" action="{{ route('customer.profile') }}">
                        @csrf
                        <div class="form-grid">
                            <div class="field">
                                <label for="name">Name</label>
                                <input id="name" name="name" autocomplete="name" value="{{ old('name', $customer->name) }}" required>
                            </div>
                            <div class="field">
                                <label for="email">Email</label>
                                <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email', $customer->email) }}">
                            </div>
                        </div>
                        <div class="field">
                            <label for="delivery_address">Delivery address</label>
                            <textarea id="delivery_address" name="delivery_address" autocomplete="street-address">{{ old('delivery_address', $customer->delivery_address) }}</textarea>
                        </div>
                        <button class="primary" type="submit">Save profile</button>
                    </form>
                </div>
            </div>
        </div>

        <aside class="card">
            <div class="card-body">
                <h2>Recent orders</h2>
                @forelse ($orders as $order)
                    <div class="order-line">
                        <span class="sku">{{ $order->order_number }}</span>
                        <span class="badge">{{ str($order->status)->headline() }}</span>
                        <span class="muted">{{ $order->created_at->format('M j, Y') }}</span>
                        <strong>LKR {{ number_format((float) $order->total, 2) }}</strong>
                    </div>
                @empty
                    <p class="muted">No orders yet.</p>
                @endforelse
            </div>
        </aside>
    </div>
</section>
@endsection
