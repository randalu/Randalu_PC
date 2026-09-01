<?php

namespace App\Models;

use App\Support\SriLankanPhone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Order extends Model
{
    public const STATUSES = ['new', 'confirmed', 'processing', 'packed', 'dispatched', 'delivered', 'cancelled'];

    public const FULFILLMENT_STATUSES = ['new', 'confirmed', 'processing', 'packed', 'dispatched', 'delivered'];

    public const ALLOWED_STATUS_TRANSITIONS = [
        'new' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['packed', 'cancelled'],
        'packed' => ['dispatched', 'cancelled'],
        'dispatched' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    protected $fillable = [
        'order_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_phone_normalized',
        'customer_email',
        'delivery_address',
        'customer_notes',
        'status',
        'payment_status',
        'subtotal',
        'delivery_fee',
        'total',
        'courier_name',
        'tracking_number',
        'delivery_notes',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * Keep the searchable E.164 copy of the phone in sync on every write
     * (checkout, admin edits, imports) so phone lookups can run in SQL.
     */
    protected static function booted(): void
    {
        static::saving(function (Order $order): void {
            $order->customer_phone_normalized = SriLankanPhone::normalize($order->customer_phone);
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EventLog::class)->latest();
    }

    public function canTransitionTo(string $status): bool
    {
        return $this->status === $status || in_array($status, self::ALLOWED_STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    public function requiresTrackingForStatus(string $status): bool
    {
        return $status === 'dispatched';
    }

    /**
     * @return array<string, Carbon>
     */
    public function publicStatusTimestamps(): array
    {
        $timestamps = ['new' => $this->created_at];
        $events = $this->relationLoaded('events')
            ? $this->events
            : $this->events()->whereIn('type', ['order.placed', 'order.status_changed'])->get();

        foreach ($events->sortBy('created_at') as $event) {
            if ($event->type === 'order.placed') {
                $timestamps['new'] = $event->created_at;
            }

            if ($event->type === 'order.status_changed' && is_array($event->metadata)) {
                $status = $event->metadata['to'] ?? null;

                if (is_string($status) && in_array($status, self::FULFILLMENT_STATUSES, true)) {
                    $timestamps[$status] = $event->created_at;
                }
            }
        }

        return $timestamps;
    }
}
