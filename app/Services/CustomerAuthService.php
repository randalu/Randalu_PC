<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Setting;
use App\Support\SriLankanPhone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CustomerAuthService
{
    private const TTL_MINUTES = 10;

    public function __construct(private readonly SmsService $sms) {}

    public function requestOtp(string $phone, string $ip): string
    {
        $normalized = $this->normalizeOrFail($phone);
        $this->ensureRateLimit($normalized, $ip);

        $otp = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($normalized), Hash::make($otp), now()->addMinutes(self::TTL_MINUTES));

        $this->sms->send($normalized, strtr(
            Setting::getValue('sms_login_otp_template', 'Your Randalu PC login OTP is {otp}. It expires in 10 minutes.'),
            ['{otp}' => $otp],
        ));

        app(EventLogger::class)->record(
            type: 'customer.otp_requested',
            summary: 'Customer login OTP sent',
            customerPhone: $normalized,
            ipAddress: $ip,
        );

        return $normalized;
    }

    public function verify(string $phone, string $otp, ?string $ip = null): Customer
    {
        $normalized = $this->normalizeOrFail($phone);
        $this->ensureVerifyRateLimit($normalized, $ip);

        $hash = Cache::get($this->cacheKey($normalized));

        if (! $hash || ! Hash::check($otp, $hash)) {
            $this->recordFailedVerify($normalized, $ip);

            app(EventLogger::class)->record(
                type: 'customer.otp_failed',
                summary: 'Customer login OTP verification failed',
                severity: 'warning',
                customerPhone: $normalized,
            );

            throw ValidationException::withMessages([
                'otp' => 'The OTP is invalid or has expired.',
            ]);
        }

        $this->clearVerifyRateLimit($normalized, $ip);
        Cache::forget($this->cacheKey($normalized));

        $customer = Customer::query()->firstOrCreate(['phone' => $normalized]);

        app(EventLogger::class)->record(
            type: $customer->wasRecentlyCreated ? 'customer.registered' : 'customer.logged_in',
            summary: $customer->wasRecentlyCreated ? 'Customer registered via SMS OTP' : 'Customer logged in via SMS OTP',
            subject: $customer,
            customerPhone: $normalized,
        );

        return $customer;
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
        foreach (["customer-otp-phone:{$phone}", "customer-otp-ip:{$ip}"] as $key) {
            if (RateLimiter::tooManyAttempts($key, 3)) {
                app(EventLogger::class)->record(
                    type: 'customer.otp_rate_limited',
                    summary: 'Customer login OTP request rate limited',
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

    private function ensureVerifyRateLimit(string $phone, ?string $ip): void
    {
        foreach ($this->verifyKeys($phone, $ip) as $key) {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                app(EventLogger::class)->record(
                    type: 'customer.otp_verify_rate_limited',
                    summary: 'Customer login OTP verification rate limited',
                    severity: 'warning',
                    customerPhone: $phone,
                    ipAddress: $ip,
                );

                throw ValidationException::withMessages([
                    'otp' => 'Too many attempts. Request a new OTP and try again.',
                ]);
            }
        }
    }

    private function recordFailedVerify(string $phone, ?string $ip): void
    {
        foreach ($this->verifyKeys($phone, $ip) as $key) {
            RateLimiter::hit($key, 600);
        }
    }

    private function clearVerifyRateLimit(string $phone, ?string $ip): void
    {
        foreach ($this->verifyKeys($phone, $ip) as $key) {
            RateLimiter::clear($key);
        }
    }

    /**
     * @return list<string>
     */
    private function verifyKeys(string $phone, ?string $ip): array
    {
        $keys = ["customer-otp-verify-phone:{$phone}"];

        if ($ip) {
            $keys[] = "customer-otp-verify-ip:{$ip}";
        }

        return $keys;
    }

    private function cacheKey(string $phone): string
    {
        return 'customer-auth-otp:'.sha1($phone);
    }
}
