<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Services\CartService;
use App\Services\CustomerAuthService;
use App\Services\EventLogger;
use App\Support\SriLankanPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function show(Request $request): RedirectResponse|View
    {
        if ($request->session()->has('customer_id')) {
            return redirect()->route('customer.account');
        }

        return view('storefront.login');
    }

    public function requestOtp(Request $request, CustomerAuthService $auth): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
        ]);

        $phone = $auth->requestOtp($data['phone'], $request->ip() ?? 'unknown');

        return back()
            ->withInput(['phone' => $phone])
            ->with('otp_phone', $phone)
            ->with('status', 'An OTP has been sent to your phone.');
    }

    public function verify(Request $request, CustomerAuthService $auth): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'otp' => ['required', 'digits:6'],
        ]);

        $customer = $auth->verify($data['phone'], $data['otp'], $request->ip() ?? 'unknown');

        // Merge any guest cart into the customer's cart before rotating the session.
        app(CartService::class)->mergeGuestCart($request->cookie('cart_token'), $customer->id);
        Cookie::queue(Cookie::forget('cart_token'));

        $request->session()->regenerate();
        $request->session()->put([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'customer_email' => $customer->email,
            'customer_address' => $customer->delivery_address,
        ]);

        return redirect()->route('customer.account')->with('status', 'Signed in.');
    }

    public function account(Request $request): RedirectResponse|View
    {
        $customer = $this->customer();

        if (! $customer) {
            return redirect()->route('customer.login');
        }

        // Orders placed while signed in are linked via customer_id; guest and
        // historical orders are found by the normalized phone copy in SQL.
        $normalized = SriLankanPhone::normalize($customer->phone);

        $orders = Order::query()
            ->where(function ($query) use ($customer, $normalized): void {
                $query->where('customer_id', $customer->id);

                if ($normalized !== null) {
                    $query->orWhere('customer_phone_normalized', $normalized);
                }
            })
            ->latest()
            ->limit(250)
            ->get();

        return view('storefront.account', [
            'customer' => $customer,
            'orders' => $orders,
        ]);
    }

    public function profile(Request $request): RedirectResponse
    {
        $customer = $this->customer();

        if (! $customer) {
            return redirect()->route('customer.login');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer->update($data);
        $request->session()->put('customer_name', $customer->name);

        app(EventLogger::class)->record(
            type: 'customer.profile_updated',
            summary: 'Customer profile updated',
            subject: $customer,
            customerPhone: $customer->phone,
        );

        return redirect()->route('customer.account')->with('status', 'Profile saved.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'customer_id',
            'customer_name',
            'customer_phone',
            'customer_email',
            'customer_address',
        ]);

        return redirect()->route('home')->with('status', 'Signed out.');
    }

    private function customer(): ?Customer
    {
        return Customer::query()->find(session('customer_id'));
    }
}
