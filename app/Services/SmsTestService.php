<?php

namespace App\Services;

class SmsTestService
{
    public function __construct(
        private readonly SmsService $sms,
        private readonly EventLogger $events,
    ) {}

    public function send(string $phone, string $message, ?int $userId = null): bool
    {
        try {
            $sent = $this->sms->send($phone, $message);

            $this->events->record(
                type: $sent ? 'sms.test_sent' : 'sms.test_skipped',
                summary: $sent ? 'Test SMS sent from admin settings' : 'Test SMS skipped from admin settings',
                severity: $sent ? 'success' : 'warning',
                userId: $userId,
                customerPhone: $phone,
            );

            return $sent;
        } catch (\Throwable $exception) {
            $this->events->record(
                type: 'sms.test_failed',
                summary: 'Test SMS failed from admin settings',
                severity: 'error',
                userId: $userId,
                customerPhone: $phone,
                metadata: [
                    'error' => $exception->getMessage(),
                ],
            );

            throw $exception;
        }
    }
}
