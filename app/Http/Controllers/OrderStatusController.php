<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderOtpService;
use App\Support\SriLankanPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderStatusController extends Controller
{
    public function show(Request $request): View
    {
        $verifiedPhone = $request->session()->get('order_status_phone');
        $orders = collect();

        if ($verifiedPhone) {
            $orders = Order::query()
                ->with([
                    'items',
                    'events' => fn ($query) => $query->whereIn('type', ['order.placed', 'order.status_changed']),
                ])
                ->latest()
                ->limit(250)
                ->get()
                ->filter(fn (Order $order): bool => SriLankanPhone::same($order->customer_phone, $verifiedPhone))
                ->values();
        }

        return view('storefront.order-status', [
            'orders' => $orders,
            'phone' => $verifiedPhone,
        ]);
    }

    public function sendOtp(Request $request, OrderOtpService $otp): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
        ]);

        $phone = $otp->send($data['phone'], $request->ip() ?? 'unknown');

        return back()
            ->withInput(['phone' => $phone])
            ->with('otp_phone', $phone)
            ->with('status', 'If we have orders for that phone number, an OTP has been sent.');
    }

    public function verify(Request $request, OrderOtpService $otp): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'otp' => ['required', 'digits:6'],
        ]);

        $phone = $otp->verify($data['phone'], $data['otp']);
        $request->session()->put('order_status_phone', $phone);

        return redirect()->route('orders.status')->with('status', 'Order status access verified.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('order_status_phone');

        return redirect()->route('orders.status');
    }
}
