@extends('layouts.storefront')

@section('title', 'Sign in / Register | Randalu PC')

@section('content')
<section class="section">
    <div class="container status-shell">
        <div class="shop-head">
            <div>
                <span class="sku">Account</span>
                <h1>Sign in / Register</h1>
                <p class="muted">Enter your phone number. We'll send you a one-time code by SMS to sign you in or create your account.</p>
            </div>
        </div>

        <div class="status-panel">
            <form method="post" action="{{ route('customer.login.request-otp') }}">
                @csrf
                <div class="field">
                    <label for="phone">Phone number</label>
                    <input id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone', session('otp_phone')) }}" placeholder="0771234567" required>
                </div>
                <button class="primary" type="submit">Send OTP</button>
            </form>

            @if (session('otp_phone'))
                <form method="post" action="{{ route('customer.login.verify') }}" class="otp-form">
                    @csrf
                    <input type="hidden" name="phone" value="{{ session('otp_phone') }}">
                    <div class="field">
                        <label for="otp">OTP</label>
                        <input id="otp" name="otp" autocomplete="one-time-code" inputmode="numeric" maxlength="6" placeholder="123456" required>
                    </div>
                    <button type="submit">Verify &amp; continue</button>
                </form>
            @endif
        </div>
    </div>
</section>
@endsection
