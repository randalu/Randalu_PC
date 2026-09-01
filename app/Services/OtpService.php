<?php

namespace App\Services;

use App\Support\SriLankanPhone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Single source of truth for OTP mechanics (normalize, rate-limit, issue,
 * verify) shared by order-status and customer-auth flows.
 *
 * Each flow passes a type constant; the per-type configuration keeps the
 * rate-limit/cache key prefixes and event-log types distinct while the logic
 * lives in exactly one place.
 */
class OtpService
{
    public const TYPE_ORDER_STATUS = 'order';

    public const TYPE_CUSTOMER = 'customer';

    private const TTL_MINUTES = 10;

    /**
     * @var array<string, array{
     *     request_limit_prefix: string,
     *     verify_limit_prefix: string,
     *     cache_prefix: string,
     *     event_request_rate_limited: string,
     *     event_verify_rate_limited: string,
     *     event_verify_failed: string,
     *     request_rate_limited_summary: string,
     *     verify_rate_limited_summary: string,
     *     verify_failed_summary: string
     * }>
     */
    private const TYPES = [
        self::TYPE_ORDER_STATUS => [
            'request_limit_prefix' => 'order-otp-',
            'verify_limit_prefix' => 'order-otp-verify-',
            'cache_prefix' => 'order-status-otp:',
            'event_request_rate_limited' => 'otp.rate_limited',
            'event_verify_rate_limited' => 'otp.verify_rate_limited',
            'event_verify_failed' => 'otp.failed',
            'request_rate_limited_summary' => 'Order status OTP request rate limited',
            'verify_rate_limited_summary' => 'Order status OTP verification rate limited',
            'verify_failed_summary' => 'Order status OTP verification failed',
        ],
        self::TYPE_CUSTOMER => [
            'request_limit_prefix' => 'customer-otp-',
            'verify_limit_prefix' => 'customer-otp-verify-',
            'cache_prefix' => 'customer-auth-otp:',
            'event_request_rate_limited' => 'customer.otp_rate_limited',
            'event_verify_rate_limited' => 'customer.otp_verify_rate_limited',
            'event_verify_failed' => 'customer.otp_failed',
            'request_rate_limited_summary' => 'Customer login OTP request rate limited',
            'verify_rate_limited_summary' => 'Customer login OTP verification rate limited',
            'verify_failed_summary' => 'Customer login OTP verification failed',
        ],
    ];

    public function normalizeOrFail(string $phone): string
    {
        $normalized = SriLankanPhone::normalize($phone);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid Sri Lankan mobile number.',
            ]);
        }

        return $normalized;
    }

    /**
     * Enforce the request-side rate limit (3 attempts per phone/IP per 5 min).
     */
    public function assertRequestAllowed(string $phone, string $ip, string $type): void
    {
        $config = $this->config($type);

        foreach ([
            $config['request_limit_prefix'].'phone:'.$phone,
            $config['request_limit_prefix'].'ip:'.$ip,
        ] as $key) {
            if (RateLimiter::tooManyAttempts($key, 3)) {
                app(EventLogger::class)->record(
                    type: $config['event_request_rate_limited'],
                    summary: $config['request_rate_limited_summary'],
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

    /**
     * Generate an OTP for the phone, cache its hash, and return the raw code.
     */
    public function issue(string $phone, string $type): string
    {
        $otp = (string) random_int(100000, 999999);

        Cache::put(
            $this->cacheKey($phone, $type),
            Hash::make($otp),
            now()->addMinutes(self::TTL_MINUTES),
        );

        return $otp;
    }

    /**
     * Verify a submitted OTP against the cached hash. Returns the normalized
     * phone on success; throws a ValidationException on failure or when the
     * verification rate limit is exceeded.
     */
    public function verify(string $phone, string $otp, string $type, ?string $ip = null): string
    {
        $normalized = $this->normalizeOrFail($phone);
        $this->assertVerifyAllowed($normalized, $ip, $type);

        $hash = Cache::get($this->cacheKey($normalized, $type));

        if (! $hash || ! Hash::check($otp, $hash)) {
            $this->recordFailedVerify($normalized, $ip, $type);

            app(EventLogger::class)->record(
                type: $this->config($type)['event_verify_failed'],
                summary: $this->config($type)['verify_failed_summary'],
                severity: 'warning',
                customerPhone: $normalized,
            );

            throw ValidationException::withMessages([
                'otp' => 'The OTP is invalid or has expired.',
            ]);
        }

        $this->clearVerifyRateLimit($normalized, $ip, $type);
        Cache::forget($this->cacheKey($normalized, $type));

        return $normalized;
    }

    private function assertVerifyAllowed(string $phone, ?string $ip, string $type): void
    {
        $config = $this->config($type);

        foreach ($this->verifyKeys($phone, $ip, $type) as $key) {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                app(EventLogger::class)->record(
                    type: $config['event_verify_rate_limited'],
                    summary: $config['verify_rate_limited_summary'],
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

    private function recordFailedVerify(string $phone, ?string $ip, string $type): void
    {
        foreach ($this->verifyKeys($phone, $ip, $type) as $key) {
            RateLimiter::hit($key, 600);
        }
    }

    private function clearVerifyRateLimit(string $phone, ?string $ip, string $type): void
    {
        foreach ($this->verifyKeys($phone, $ip, $type) as $key) {
            RateLimiter::clear($key);
        }
    }

    /**
     * @return list<string>
     */
    private function verifyKeys(string $phone, ?string $ip, string $type): array
    {
        $prefix = $this->config($type)['verify_limit_prefix'];
        $keys = [$prefix.'phone:'.$phone];

        if ($ip) {
            $keys[] = $prefix.'ip:'.$ip;
        }

        return $keys;
    }

    private function cacheKey(string $phone, string $type): string
    {
        return $this->config($type)['cache_prefix'].sha1($phone);
    }

    /**
     * @return array{
     *     request_limit_prefix: string,
     *     verify_limit_prefix: string,
     *     cache_prefix: string,
     *     event_request_rate_limited: string,
     *     event_verify_rate_limited: string,
     *     event_verify_failed: string,
     *     request_rate_limited_summary: string,
     *     verify_rate_limited_summary: string,
     *     verify_failed_summary: string
     * }
     */
    private function config(string $type): array
    {
        if (! isset(self::TYPES[$type])) {
            throw new InvalidArgumentException("Unknown OTP type [{$type}].");
        }

        return self::TYPES[$type];
    }
}
