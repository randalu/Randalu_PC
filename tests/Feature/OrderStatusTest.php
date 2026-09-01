<?php

namespace Tests\Feature;

use App\Jobs\SendSms;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\OrderStatusService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class OrderStatusTest extends FeatureTestCase
{
    public function test_confirming_order_deducts_stock_once_and_logs_inventory(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();
        $variant = ProductVariant::query()->firstOrFail();

        $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->post('/checkout', [
            'customer_name' => 'Stock Customer',
            'customer_phone' => '0777654321',
            'delivery_address' => 'Negombo',
        ]);

        $order = Order::query()->where('customer_phone', '0777654321')->firstOrFail();

        app(OrderStatusService::class)->update($order, [
            'status' => 'confirmed',
            'payment_status' => 'cod_pending',
            'delivery_fee' => 350,
        ], $admin->id);

        app(OrderStatusService::class)->update($order->refresh(), [
            'status' => 'processing',
            'payment_status' => 'cod_pending',
            'delivery_fee' => 350,
        ], $admin->id);

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock_quantity' => 8]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'order_id' => $order->id,
            'quantity_change' => -2,
            'stock_after' => 8,
            'reason' => 'order_confirmed',
        ]);
        $this->assertSame(1, $order->movements()->count());
        $this->assertDatabaseHas('event_logs', [
            'type' => 'order.status_changed',
            'order_id' => $order->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_order_status_cannot_jump_ahead(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();
        $order = $this->placeOrder('Jump Customer', '0772222222');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Order cannot move from new to delivered.');

        app(OrderStatusService::class)->update($order, [
            'status' => 'delivered',
        ], $admin->id);
    }

    public function test_rejected_order_status_changes_are_logged(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();
        $order = $this->placeOrder('Rejected Customer', '0773333333');

        try {
            app(OrderStatusService::class)->update($order, [
                'status' => 'delivered',
            ], $admin->id);
        } catch (RuntimeException) {
            //
        }

        $this->assertDatabaseHas('event_logs', [
            'type' => 'order.status_rejected',
            'severity' => 'warning',
            'order_id' => $order->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_dispatch_requires_courier_and_tracking_number(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();
        $order = $this->advanceOrderToPacked($this->placeOrder('Dispatch Customer', '0774444444'), $admin);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Courier name and tracking number are required before dispatching an order.');

        app(OrderStatusService::class)->update($order, [
            'status' => 'dispatched',
        ], $admin->id);
    }

    public function test_dispatch_succeeds_with_courier_and_tracking_number(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();
        $order = $this->advanceOrderToPacked($this->placeOrder('Tracked Customer', '0775555555'), $admin);

        $updated = app(OrderStatusService::class)->update($order, [
            'status' => 'dispatched',
            'courier_name' => 'Domestic Courier',
            'tracking_number' => 'TRK123',
        ], $admin->id);

        $this->assertSame('dispatched', $updated->status);
        $this->assertSame('Domestic Courier', $updated->courier_name);
        $this->assertSame('TRK123', $updated->tracking_number);
    }

    public function test_customer_can_verify_phone_by_sms_otp_and_view_matching_orders(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully'])]);

        $visibleOrder = $this->placeOrder('Visible Customer', '0771234567');
        $this->placeOrder('Other Customer', '0779999999');
        $admin = User::query()->firstOrFail();
        app(OrderStatusService::class)->update($visibleOrder, [
            'status' => 'confirmed',
        ], $admin->id);

        $this->post(route('orders.status.send-otp'), ['phone' => '94771234567'])
            ->assertRedirect()
            ->assertSessionHas('otp_phone', '+94771234567');

        $sentOtp = null;
        Http::assertSent(function (Request $request) use (&$sentOtp): bool {
            preg_match('/\b(\d{6})\b/', (string) $request['message'], $matches);
            $sentOtp = $matches[1] ?? null;

            return $request->url() === 'https://smslenz.lk/api/send-sms'
                && $request['user_id'] === '1557'
                && $request['sender_id'] === 'SMSlenzDEMO'
                && $request['contact'] === '+94771234567'
                && $sentOtp !== null;
        });

        $this->post(route('orders.status.verify'), [
            'phone' => '0771234567',
            'otp' => $sentOtp,
        ])->assertRedirect(route('orders.status'));

        $this->get(route('orders.status'))
            ->assertOk()
            ->assertSee($visibleOrder->order_number)
            ->assertSee('Visible Customer')
            ->assertSee('Order received')
            ->assertSee('Confirmed')
            ->assertDontSee('Other Customer');

        $this->assertDatabaseHas('event_logs', ['type' => 'otp.requested', 'customer_phone' => '+94771234567']);
        $this->assertDatabaseHas('event_logs', ['type' => 'otp.verified', 'customer_phone' => '+94771234567']);
        $this->assertDatabaseHas('event_logs', ['type' => 'sms.sent', 'customer_phone' => '+94771234567']);
    }

    public function test_order_public_status_timestamps_are_built_from_events(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();
        $order = $this->placeOrder('Timeline Customer', '0771212121');

        app(OrderStatusService::class)->update($order, [
            'status' => 'confirmed',
        ], $admin->id);

        $timestamps = $order->refresh()->load('events')->publicStatusTimestamps();

        $this->assertArrayHasKey('new', $timestamps);
        $this->assertArrayHasKey('confirmed', $timestamps);
        $this->assertTrue($order->events()->where('type', 'order.status_changed')->exists());
    }

    public function test_expired_or_invalid_order_status_otp_is_rejected(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully'])]);

        $this->placeOrder('OTP Customer', '0771234567');
        $this->post(route('orders.status.send-otp'), ['phone' => '0771234567']);

        Cache::flush();

        $this->post(route('orders.status.verify'), [
            'phone' => '0771234567',
            'otp' => '123456',
        ])->assertSessionHasErrors('otp');

        $this->assertDatabaseHas('event_logs', [
            'type' => 'otp.failed',
            'severity' => 'warning',
            'customer_phone' => '+94771234567',
        ]);
    }

    public function test_order_status_otp_requests_are_rate_limited(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully'])]);

        $this->placeOrder('Limited Customer', '0771234567');

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('orders.status.send-otp'), ['phone' => '0771234567'])->assertSessionHasNoErrors();
        }

        $this->post(route('orders.status.send-otp'), ['phone' => '0771234567'])
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseHas('event_logs', [
            'type' => 'otp.rate_limited',
            'severity' => 'warning',
            'customer_phone' => '+94771234567',
        ]);
    }

    public function test_order_status_update_sends_customer_sms_when_enabled(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully'])]);

        $admin = User::query()->firstOrFail();
        $order = $this->placeOrder('SMS Customer', '0771234567');

        app(OrderStatusService::class)->update($order, [
            'status' => 'confirmed',
            'payment_status' => 'cod_pending',
            'delivery_fee' => 350,
        ], $admin->id);

        Http::assertSent(fn (Request $request): bool => $request['contact'] === '+94771234567'
            && str_contains((string) $request['message'], $order->order_number)
            && str_contains((string) $request['message'], 'Confirmed'));

        $this->assertDatabaseHas('event_logs', [
            'type' => 'sms.sent',
            'customer_phone' => '+94771234567',
        ]);
    }

    public function test_order_status_sms_is_dispatched_as_a_job(): void
    {
        $this->seed();
        $this->enableSms();
        Queue::fake();

        $admin = User::query()->firstOrFail();
        $order = $this->placeOrder('Queued SMS', '0771234567');

        app(OrderStatusService::class)->update($order, ['status' => 'confirmed'], $admin->id);

        Queue::assertPushed(SendSms::class, fn (SendSms $job): bool =>
            $job->phone === '+94771234567'
            && str_contains($job->message, $order->order_number));
    }

    public function test_cancelling_a_confirmed_order_restores_stock(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();
        $variant = ProductVariant::query()->firstOrFail();
        $initialStock = $variant->stock_quantity;

        $order = $this->placeOrder('Cancel Customer', '0771234567');

        app(OrderStatusService::class)->update($order, ['status' => 'confirmed'], $admin->id);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock_quantity' => $initialStock - 1]);

        app(OrderStatusService::class)->update($order->refresh(), ['status' => 'cancelled'], $admin->id);

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock_quantity' => $initialStock]);
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'order_id' => $order->id,
            'quantity_change' => 1,
            'reason' => 'order_cancelled',
        ]);
    }

    public function test_cancelling_a_new_order_does_not_restock(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();
        $order = $this->placeOrder('New Cancel', '0779999999');

        app(OrderStatusService::class)->update($order, ['status' => 'cancelled'], $admin->id);

        $this->assertDatabaseMissing('inventory_movements', [
            'order_id' => $order->id,
            'reason' => 'order_cancelled',
        ]);
    }

    public function test_order_status_otp_verification_is_rate_limited(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully'])]);

        RateLimiter::clear('order-otp-verify-phone:+94771234567');
        RateLimiter::clear('order-otp-verify-ip:127.0.0.1');

        $this->placeOrder('Verify Limit', '0771234567');
        $this->post(route('orders.status.send-otp'), ['phone' => '0771234567'])->assertSessionHasNoErrors();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('orders.status.verify'), ['phone' => '0771234567', 'otp' => '000000'])
                ->assertSessionHasErrors('otp');
        }

        $this->post(route('orders.status.verify'), ['phone' => '0771234567', 'otp' => '000000'])
            ->assertSessionHasErrors('otp');

        $this->assertDatabaseHas('event_logs', [
            'type' => 'otp.verify_rate_limited',
            'severity' => 'warning',
            'customer_phone' => '+94771234567',
        ]);
    }

    public function test_phone_lookups_use_the_normalized_e164_column(): void
    {
        $this->seed();

        // Order stored with a non-standard display format for the same number.
        $order = $this->placeOrder('Format Mix Customer', '94 77 123 4567');

        $this->assertSame('+94771234567', $order->customer_phone_normalized);

        $this->withSession(['order_status_phone' => '+94771234567'])
            ->get(route('orders.status'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Format Mix Customer');
    }

    public function test_phone_edits_resync_the_normalized_column(): void
    {
        $this->seed();
        $order = $this->placeOrder('Resync Customer', '0771234567');

        $this->assertSame('+94771234567', $order->customer_phone_normalized);

        $order->update(['customer_phone' => '0761234567']);

        $this->assertSame('+94761234567', $order->refresh()->customer_phone_normalized);
    }
}
