<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Support\SriLankanPhone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OrderOtpService
{
    private const TTL_MINUTES = 10;

    public function __construct(private readonly SmsService $sms) {}

    public function send(string $phone, string $ip): string
    {
        $normalized = $this->normalizeOrFail($phone);
        $this->ensureRateLimit($normalized, $ip);

        if (! $this->hasOrdersForPhone($normalized)) {
            app(EventLogger::class)->record(
                type: 'otp.requested_no_orders',
                summary: 'Order status OTP requested for phone with no recent orders',
                severity: 'warning',
                customerPhone: $normalized,
                ipAddress: $ip,
            );

            return $normalized;
        }

        $otp = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($normalized), Hash::make($otp), now()->addMinutes(self::TTL_MINUTES));

        $this->sms->send($normalized, strtr(
            Setting::getValue('sms_otp_template', 'Your PMS order status OTP is {otp}. It expires in 10 minutes.'),
            ['{otp}' => $otp],
        ));

        app(EventLogger::class)->record(
            type: 'otp.requested',
            summary: 'Order status OTP sent',
            customerPhone: $normalized,
            ipAddress: $ip,
        );

        return $normalized;
    }

    public function verify(string $phone, string $otp): string
    {
        $normalized = $this->normalizeOrFail($phone);
        $hash = Cache::get($this->cacheKey($normalized));

        if (! $hash || ! Hash::check($otp, $hash)) {
            app(EventLogger::class)->record(
                type: 'otp.failed',
                summary: 'Order status OTP verification failed',
                severity: 'warning',
                customerPhone: $normalized,
            );

            throw ValidationException::withMessages([
                'otp' => 'The OTP is invalid or has expired.',
            ]);
        }

        Cache::forget($this->cacheKey($normalized));

        app(EventLogger::class)->record(
            type: 'otp.verified',
            summary: 'Order status OTP verified',
            customerPhone: $normalized,
        );

        return $normalized;
    }

    private function normalizeOrFail(string $phone): string
    {
        $normalized = SriLankanPhone::normalize($phone);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid Sri Lankan mobile number.',
            ]);
        }

        return $normalized;
    }

    private function ensureRateLimit(string $phone, string $ip): void
    {
        foreach (["order-otp-phone:{$phone}", "order-otp-ip:{$ip}"] as $key) {
            if (RateLimiter::tooManyAttempts($key, 3)) {
                app(EventLogger::class)->record(
                    type: 'otp.rate_limited',
                    summary: 'Order status OTP request rate limited',
                    severity: 'warning',
                    customerPhone: $phone,
                    ipAddress: $ip,
                );

                throw ValidationException::withMessages([
                    'phone' => 'Too many OTP requests. Please try again later.',
                ]);
            }

            RateLimiter::hit($key, 300);
        }
    }

    private function hasOrdersForPhone(string $phone): bool
    {
        return Order::query()
            ->latest()
            ->limit(250)
            ->get(['customer_phone'])
            ->contains(fn (Order $order): bool => SriLankanPhone::same($order->customer_phone, $phone));
    }

    private function cacheKey(string $phone): string
    {
        return 'order-status-otp:'.sha1($phone);
    }
}
