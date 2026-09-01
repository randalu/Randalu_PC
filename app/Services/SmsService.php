<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\SriLankanPhone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SmsService
{
    public function isEnabled(): bool
    {
        return filter_var(Setting::getValue('sms_enabled', config('services.smslenz.enabled') ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public function send(string $phone, string $message): bool
    {
        if (! $this->isEnabled()) {
            app(EventLogger::class)->record(
                type: 'sms.skipped',
                summary: 'SMS skipped because SMS is disabled',
                severity: 'warning',
                customerPhone: $phone,
            );

            Log::info('SMS skipped because SMS is disabled.', ['phone' => $phone]);

            return false;
        }

        $contact = SriLankanPhone::normalize($phone);
        if ($contact === null) {
            throw new RuntimeException('Phone number must be a valid Sri Lankan mobile number.');
        }

        // SMSlenz allows messages up to 1500 characters.
        $message = str($message)->limit(1500, '')->toString();
        $userId = config('services.smslenz.user_id');
        $apiKey = config('services.smslenz.api_key');
        $senderId = Setting::getValue('sms_sender_id', (string) config('services.smslenz.sender_id'));

        if (! $userId || ! $apiKey || ! $senderId) {
            app(EventLogger::class)->record(
                type: 'sms.failed',
                summary: 'SMSlenz credentials are not configured',
                severity: 'error',
                customerPhone: $contact,
            );

            throw new RuntimeException('SMSlenz credentials are not configured.');
        }

        $response = Http::timeout(15)->asForm()->post(rtrim((string) config('services.smslenz.base_url'), '/').'/send-sms', [
            'user_id' => $userId,
            'api_key' => $apiKey,
            'sender_id' => $senderId,
            'contact' => $contact,
            'message' => $message,
        ]);

        // SMSlenz can return HTTP 200 with a JSON `success: false` payload
        // (insufficient credit, rejected sender, etc.), so validate the body.
        $success = $response->successful() && (bool) $response->json('success', false);

        if (! $success) {
            app(EventLogger::class)->record(
                type: 'sms.failed',
                summary: "SMSlenz send failed (HTTP {$response->status()})",
                severity: 'error',
                customerPhone: $contact,
                metadata: [
                    'status' => $response->status(),
                    'body' => str($response->body())->limit(500)->toString(),
                ],
            );

            Log::warning('SMSlenz send failed.', [
                'phone' => $contact,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        app(EventLogger::class)->record(
            type: 'sms.sent',
            summary: 'SMS sent through SMSlenz',
            customerPhone: $contact,
            metadata: [
                'status' => $response->status(),
                'sender_id' => $senderId,
                'campaign_id' => $response->json('data.campaign_id'),
                'pages' => $response->json('data.pages'),
                'balance' => $response->json('data.sms_credit_balance'),
            ],
        );

        return true;
    }

    /**
     * Query the SMSlenz account-status endpoint.
     *
     * @return array<string, mixed>
     */
    public function accountStatus(): array
    {
        $userId = config('services.smslenz.user_id');
        $apiKey = config('services.smslenz.api_key');

        if (! $userId || ! $apiKey) {
            throw new RuntimeException('SMSlenz credentials are not configured.');
        }

        $response = Http::timeout(15)->asForm()->post(rtrim((string) config('services.smslenz.base_url'), '/').'/account-status', [
            'user_id' => $userId,
            'api_key' => $apiKey,
        ]);

        if ($response->failed() || ! (bool) $response->json('success', false)) {
            Log::warning('SMSlenz account status check failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('SMSlenz account status check failed (HTTP '.$response->status().').');
        }

        return $response->json('data', []);
    }
}
