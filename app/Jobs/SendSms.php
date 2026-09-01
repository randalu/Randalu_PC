<?php

namespace App\Jobs;

use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSms implements ShouldQueue
{
    use Queueable;

    /**
     * Number of attempts before the job is marked as failed.
     */
    public int $tries = 3;

    /**
     * Backoff schedule in seconds between retries.
     *
     * @var list<int>
     */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public string $phone,
        public string $message,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SmsService $sms): void
    {
        // NOTE: retries can result in a duplicate send for status SMS. This is
        // accepted for order-status notifications (see README); OTP sends remain
        // synchronous because a 10-minute code cannot wait on a queue worker.
        $sms->send($this->phone, $this->message);
    }
}
