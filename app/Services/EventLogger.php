<?php

namespace App\Services;

use App\Models\EventLog;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class EventLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $type,
        string $summary,
        string $severity = 'info',
        ?Model $subject = null,
        ?Order $order = null,
        ?int $userId = null,
        ?string $customerPhone = null,
        ?string $ipAddress = null,
        array $metadata = [],
    ): void {
        try {
            EventLog::query()->create([
                'type' => $type,
                'severity' => $severity,
                'summary' => $summary,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'order_id' => $order?->id,
                'user_id' => $userId,
                'customer_phone' => $customerPhone,
                'ip_address' => $ipAddress,
                'metadata' => $metadata === [] ? null : $metadata,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Event log write failed.', [
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
