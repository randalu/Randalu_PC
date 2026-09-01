<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Support\SriLankanPhone;

class OrderOtpService
{
    public function __construct(
        private readonly SmsService $sms,
        private readonly OtpService $otp,
    ) {}

    public function send(string $phone, string $ip): string
    {
        $normalized = $this->otp->normalizeOrFail($phone);
        $this->otp->assertRequestAllowed($normalized, $ip, OtpService::TYPE_ORDER_STATUS);

        // Do not reveal whether a phone has orders: silently skip sending when
        // there is no recent order for this number.
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

        $code = $this->otp->issue($normalized, OtpService::TYPE_ORDER_STATUS);

        $this->sms->send($normalized, strtr(
            Setting::getValue('sms_otp_template', 'Your Randalu PC order status OTP is {otp}. It expires in 10 minutes.'),
            ['{otp}' => $code],
        ));

        app(EventLogger::class)->record(
            type: 'otp.requested',
            summary: 'Order status OTP sent',
            customerPhone: $normalized,
            ipAddress: $ip,
        );

        return $normalized;
    }

    public function verify(string $phone, string $otp, ?string $ip = null): string
    {
        $normalized = $this->otp->verify($phone, $otp, OtpService::TYPE_ORDER_STATUS, $ip);

        app(EventLogger::class)->record(
            type: 'otp.verified',
            summary: 'Order status OTP verified',
            customerPhone: $normalized,
        );

        return $normalized;
    }

    private function hasOrdersForPhone(string $phone): bool
    {
        return Order::query()
            ->latest()
            ->limit(250)
            ->get(['customer_phone'])
            ->contains(fn (Order $order): bool => SriLankanPhone::same($order->customer_phone, $phone));
    }
}
