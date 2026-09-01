<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Setting;

class CustomerAuthService
{
    public function __construct(
        private readonly SmsService $sms,
        private readonly OtpService $otp,
    ) {}

    public function requestOtp(string $phone, string $ip): string
    {
        $normalized = $this->otp->normalizeOrFail($phone);
        $this->otp->assertRequestAllowed($normalized, $ip, OtpService::TYPE_CUSTOMER);

        $code = $this->otp->issue($normalized, OtpService::TYPE_CUSTOMER);

        $this->sms->send($normalized, strtr(
            Setting::getValue('sms_login_otp_template', 'Your Randalu PC login OTP is {otp}. It expires in 10 minutes.'),
            ['{otp}' => $code],
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
        $normalized = $this->otp->verify($phone, $otp, OtpService::TYPE_CUSTOMER, $ip);

        $customer = Customer::query()->firstOrCreate(['phone' => $normalized]);

        app(EventLogger::class)->record(
            type: $customer->wasRecentlyCreated ? 'customer.registered' : 'customer.logged_in',
            summary: $customer->wasRecentlyCreated ? 'Customer registered via SMS OTP' : 'Customer logged in via SMS OTP',
            subject: $customer,
            customerPhone: $normalized,
        );

        return $customer;
    }
}
