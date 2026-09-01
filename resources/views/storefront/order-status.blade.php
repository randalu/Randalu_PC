@extends('layouts.storefront')

@section('title', 'Check order status | Priyanthi Multi Stores')

@section('content')
<section class="section">
    <div class="container status-shell">
        <div>
            <h1>Check order status</h1>
            <p class="muted">Use the phone number from checkout to view recent order progress.</p>
        </div>

        @if (! $phone)
            <div class="status-panel">
                <form method="post" action="{{ route('orders.status.send-otp') }}">
                    @csrf
                    <div class="field">
                        <label for="phone">Phone number</label>
                        <input id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone', session('otp_phone')) }}" placeholder="0771234567" required>
                    </div>
                    <button class="primary" type="submit">Send OTP</button>
                </form>

                @if (session('otp_phone'))
                    <form method="post" action="{{ route('orders.status.verify') }}" class="otp-form">
                        @csrf
                        <input type="hidden" name="phone" value="{{ session('otp_phone') }}">
                        <div class="field">
                            <label for="otp">OTP</label>
                            <input id="otp" name="otp" autocomplete="one-time-code" inputmode="numeric" maxlength="6" placeholder="123456" required>
                        </div>
                        <button type="submit">Verify</button>
                    </form>
                @endif
            </div>
        @else
            <div class="status-actions">
                <span class="badge good">Verified {{ $phone }}</span>
                <form method="post" action="{{ route('orders.status.logout') }}">
                    @csrf
                    @method('delete')
                    <button type="submit">Use another phone</button>
                </form>
            </div>

            <div class="order-list">
                @forelse ($orders as $order)
                    <article class="order-card">
                        <div class="order-card-head">
                            <div>
                                <span class="sku">{{ $order->order_number }}</span>
                                <h2>{{ str($order->status)->headline() }}</h2>
                                <p>{{ $order->customer_name }}</p>
                                <p class="muted">Placed {{ $order->created_at->format('M j, Y g:i A') }}</p>
                            </div>
                            <strong>LKR {{ number_format((float) $order->total, 2) }}</strong>
                        </div>

                        <div class="status-grid">
                            <span><strong>Payment</strong>{{ str($order->payment_status)->headline() }}</span>
                            <span><strong>Courier</strong>{{ $order->courier_name ?: '-' }}</span>
                            <span><strong>Tracking</strong>{{ $order->tracking_number ?: '-' }}</span>
                        </div>

                        @php
                            $steps = \App\Models\Order::FULFILLMENT_STATUSES;
                            $currentStep = array_search($order->status, $steps, true);
                            $isCancelled = $order->status === 'cancelled';
                            $statusTimestamps = $order->publicStatusTimestamps();
                        @endphp
                        <ol class="status-timeline {{ $isCancelled ? 'cancelled' : '' }}">
                            @foreach ($steps as $index => $step)
                                <li class="{{ (! $isCancelled && $currentStep !== false && $index <= $currentStep) ? 'done' : '' }}">
                                    <span>{{ $index + 1 }}</span>
                                    <strong>{{ $step === 'new' ? 'Order received' : str($step)->headline() }}</strong>
                                    @if (isset($statusTimestamps[$step]) && ! $isCancelled)
                                        <small>{{ $statusTimestamps[$step]->format('M j, g:i A') }}</small>
                                    @endif
                                </li>
                            @endforeach
                        </ol>

                        @if ($isCancelled)
                            <p class="errors">This order has been cancelled.</p>
                        @endif

                        @if ($order->delivery_notes)
                            <p class="notice">{{ $order->delivery_notes }}</p>
                        @endif

                        <div class="table-wrap">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Size</th>
                                        <th>Qty</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($order->items as $item)
                                        <tr>
                                            <td>{{ $item->product_name }}<br><span class="muted">{{ $item->sku }} / {{ $item->color }}</span></td>
                                            <td>{{ $item->size }}</td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>LKR {{ number_format((float) $item->line_total, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </article>
                @empty
                    <div class="status-panel">
                        <p>No recent orders were found for this phone number.</p>
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</section>
@endsection
