<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderStatusService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Order $order, array $data, ?int $userId = null): Order
    {
        try {
            $result = DB::transaction(function () use ($order, $data, $userId): array {
                $order = Order::query()->lockForUpdate()->findOrFail($order->id);
                $previousStatus = $order->status;
                $nextStatus = $data['status'] ?? $order->status;

                $this->validateStatusChange($order, $data, $nextStatus);

                $confirming = $nextStatus === 'confirmed' && $order->confirmed_at === null;

                if ($confirming) {
                    $items = $order->items()->with('variant')->get();

                    foreach ($items as $item) {
                        $variant = $item->variant()->lockForUpdate()->first();

                        if (! $variant || $variant->stock_quantity < $item->quantity) {
                            throw new RuntimeException("Not enough stock for {$item->sku} {$item->size} {$item->color}.");
                        }
                    }

                    foreach ($items as $item) {
                        $variant = $item->variant()->lockForUpdate()->firstOrFail();
                        $variant->decrement('stock_quantity', $item->quantity);
                        $variant->refresh();

                        InventoryMovement::query()->create([
                            'product_variant_id' => $variant->id,
                            'order_id' => $order->id,
                            'quantity_change' => -$item->quantity,
                            'stock_after' => $variant->stock_quantity,
                            'reason' => 'order_confirmed',
                            'note' => "Order {$order->order_number}",
                            'user_id' => $userId,
                        ]);
                    }

                    $data['confirmed_at'] = now();
                }

                if (array_key_exists('delivery_fee', $data)) {
                    $data['total'] = (float) $order->subtotal + (float) $data['delivery_fee'];
                }

                $order->update($data);

                return [
                    'order' => $order->refresh(),
                    'previous_status' => $previousStatus,
                ];
            });
        } catch (RuntimeException $exception) {
            if (array_key_exists('status', $data)) {
                app(EventLogger::class)->record(
                    type: 'order.status_rejected',
                    summary: "Order {$order->order_number} was not moved to {$data['status']}",
                    severity: 'warning',
                    subject: $order,
                    order: $order,
                    userId: $userId,
                    customerPhone: $order->customer_phone,
                    metadata: [
                        'from' => $order->status,
                        'to' => $data['status'],
                        'error' => $exception->getMessage(),
                    ],
                );
            }

            throw $exception;
        }

        /** @var Order $updatedOrder */
        $updatedOrder = $result['order'];

        if (($result['previous_status'] ?? null) !== $updatedOrder->status) {
            app(EventLogger::class)->record(
                type: 'order.status_changed',
                summary: "Order {$updatedOrder->order_number} changed from {$result['previous_status']} to {$updatedOrder->status}",
                subject: $updatedOrder,
                order: $updatedOrder,
                userId: $userId,
                customerPhone: $updatedOrder->customer_phone,
                metadata: [
                    'from' => $result['previous_status'],
                    'to' => $updatedOrder->status,
                ],
            );

            app(OrderSmsNotifier::class)->sendStatusUpdate($updatedOrder);
        }

        return $updatedOrder;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateStatusChange(Order $order, array $data, string $nextStatus): void
    {
        if (! in_array($nextStatus, Order::STATUSES, true)) {
            throw new RuntimeException('Unknown order status.');
        }

        if (! $order->canTransitionTo($nextStatus)) {
            throw new RuntimeException("Order cannot move from {$order->status} to {$nextStatus}.");
        }

        $courierName = trim((string) ($data['courier_name'] ?? $order->courier_name ?? ''));
        $trackingNumber = trim((string) ($data['tracking_number'] ?? $order->tracking_number ?? ''));

        if ($order->requiresTrackingForStatus($nextStatus) && ($courierName === '' || $trackingNumber === '')) {
            throw new RuntimeException('Courier name and tracking number are required before dispatching an order.');
        }
    }
}
