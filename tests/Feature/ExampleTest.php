<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ProductVariants\ProductVariantResource;
use App\Filament\Resources\Settings\SettingResource;
use App\Models\Customer;
use App\Models\EventLog;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderStatusService;
use App\Services\SmsService;
use App\Services\SmsTestService;
use App\Support\SriLankanPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_lists_seeded_products(): void
    {
        $this->seed();

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Shop Hardware')
            ->assertSee('RPC-LAP-01');
    }

    public function test_seeded_products_have_active_variants(): void
    {
        $this->seed();

        $variants = ProductVariant::query()
            ->where('is_active', true)
            ->orderBy('product_id')
            ->get();

        $this->assertNotEmpty($variants);

        foreach ($variants as $variant) {
            $this->assertNotSame('', trim($variant->size));
            $this->assertGreaterThan(0, (float) $variant->price);
        }
    }

    public function test_product_variant_selection_defaults_to_select_variant(): void
    {
        $this->seed();
        $product = ProductVariant::query()->firstOrFail()->product;

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Select variant')
            ->assertSee('Genuine hardware with warranty support.');
    }

    public function test_customer_can_place_online_order(): void
    {
        $this->seed();
        $variant = ProductVariant::query()->firstOrFail();

        $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 2])->assertRedirect('/cart');

        $this->post('/checkout', [
            'customer_name' => 'Test Customer',
            'customer_phone' => '0771234567',
            'customer_email' => 'customer@example.com',
            'delivery_address' => 'Katunayake',
            'customer_notes' => 'Call before delivery',
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', ['customer_phone' => '0771234567', 'status' => 'new']);
        $this->assertDatabaseHas('order_items', ['product_variant_id' => $variant->id, 'quantity' => 2]);
        $this->assertDatabaseHas('event_logs', [
            'type' => 'order.placed',
            'customer_phone' => '0771234567',
        ]);
    }

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

    public function test_sri_lankan_phone_numbers_are_normalized_for_sms(): void
    {
        $this->assertSame('+94771234567', SriLankanPhone::normalize('0771234567'));
        $this->assertSame('+94771234567', SriLankanPhone::normalize('94771234567'));
        $this->assertSame('+94771234567', SriLankanPhone::normalize('+94 77 123 4567'));
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

    public function test_sms_settings_are_limited_to_super_admins(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($admin);
        $this->assertTrue(SettingResource::canViewAny());

        $this->actingAs($staff);
        $this->assertFalse(SettingResource::canViewAny());
    }

    public function test_settings_updates_are_logged_without_storing_secret_values(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();
        $this->actingAs($admin);

        Setting::query()->updateOrCreate(['key' => 'sms_sender_id'], ['value' => 'PeachTreeLK']);

        $event = EventLog::query()->where('type', 'setting.updated')->latest()->firstOrFail();

        $this->assertSame('Setting sms_sender_id updated', $event->summary);
        $this->assertSame($admin->id, $event->user_id);
        $this->assertSame('sms_sender_id', $event->metadata['key']);
        $this->assertArrayNotHasKey('value', $event->metadata);
    }

    public function test_admin_test_sms_is_sent_and_logged(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully'])]);

        $admin = User::query()->firstOrFail();

        $sent = app(SmsTestService::class)->send('0771234567', 'Randalu PC SMS test message.', $admin->id);

        $this->assertTrue($sent);
        Http::assertSent(fn (Request $request): bool => $request['contact'] === '+94771234567'
            && $request['message'] === 'Randalu PC SMS test message.');
        $this->assertDatabaseHas('event_logs', [
            'type' => 'sms.test_sent',
            'severity' => 'success',
            'user_id' => $admin->id,
            'customer_phone' => '0771234567',
        ]);
    }

    public function test_admin_dashboard_requires_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_is_required_to_set_up_two_factor_authentication(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect('/admin/multi-factor-authentication/set-up');
    }

    public function test_customer_can_register_with_sms_otp(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully'])]);

        $this->post(route('customer.login.request-otp'), ['phone' => '0771234567'])
            ->assertRedirect()
            ->assertSessionHas('otp_phone', '+94771234567');

        $sentOtp = null;
        Http::assertSent(function (Request $request) use (&$sentOtp): bool {
            preg_match('/\b(\d{6})\b/', (string) $request['message'], $matches);
            $sentOtp = $matches[1] ?? null;

            return $request->url() === 'https://smslenz.lk/api/send-sms' && $sentOtp !== null;
        });

        $this->post(route('customer.login.verify'), [
            'phone' => '0771234567',
            'otp' => $sentOtp,
        ])->assertRedirect(route('customer.account'));

        $this->assertDatabaseHas('customers', ['phone' => '+94771234567']);
        $this->assertNotNull(session('customer_id'));
        $this->assertDatabaseHas('event_logs', ['type' => 'customer.registered', 'customer_phone' => '+94771234567']);
    }

    public function test_existing_customer_can_login_and_view_their_orders(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully'])]);

        $order = $this->placeOrder('Existing Customer', '0771234567');
        Customer::query()->create(['phone' => '+94771234567', 'name' => 'Existing Customer']);

        $this->post(route('customer.login.request-otp'), ['phone' => '0771234567'])->assertRedirect();

        $sentOtp = null;
        Http::assertSent(function (Request $request) use (&$sentOtp): bool {
            preg_match('/\b(\d{6})\b/', (string) $request['message'], $matches);
            $sentOtp = $matches[1] ?? null;

            return $sentOtp !== null;
        });

        $this->post(route('customer.login.verify'), [
            'phone' => '0771234567',
            'otp' => $sentOtp,
        ])->assertRedirect(route('customer.account'));

        $this->assertDatabaseHas('event_logs', ['type' => 'customer.logged_in', 'customer_phone' => '+94771234567']);

        $this->get(route('customer.account'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Existing Customer');
    }

    public function test_customer_can_complete_profile_after_registration(): void
    {
        $this->seed();
        $customer = Customer::query()->create(['phone' => '+94771234567']);

        $this->withSession(['customer_id' => $customer->id])
            ->post(route('customer.profile'), [
                'name' => 'Kasun Perera',
                'email' => 'kasun@example.com',
                'delivery_address' => 'Negombo',
            ])->assertRedirect(route('customer.account'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Kasun Perera',
            'delivery_address' => 'Negombo',
        ]);
    }

    public function test_sms_send_treats_success_false_as_failure(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Insufficient credit'])]);

        $sent = app(SmsService::class)->send('0771234567', 'Hello');

        $this->assertFalse($sent);
        $this->assertDatabaseHas('event_logs', ['type' => 'sms.failed', 'severity' => 'error']);
    }

    public function test_reseeding_does_not_overwrite_existing_admin_password(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();

        DB::table('users')->where('id', $admin->id)->update(['password' => 'existing-hash-value']);

        $this->seed();

        $this->assertSame('existing-hash-value', User::query()->find($admin->id)->password);
    }

    public function test_seeding_production_without_admin_password_fails(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);

        $this->seed();
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

    public function test_staff_cannot_manage_orders_or_inventory(): void
    {
        $this->seed();
        $order = $this->placeOrder('Auth Check', '0771234567');
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($staff);

        $this->assertFalse(OrderResource::canViewAny());
        $this->assertFalse(OrderResource::canEdit($order));
        $this->assertFalse(ProductVariantResource::canCreate());
        $this->assertFalse(ProductVariantResource::canEdit(ProductVariant::query()->firstOrFail()));
    }

    public function test_admin_can_manage_orders_and_inventory(): void
    {
        $this->seed();
        $order = $this->placeOrder('Admin Check', '0772222222');
        $admin = User::query()->firstOrFail();

        $this->actingAs($admin);

        $this->assertTrue(OrderResource::canViewAny());
        $this->assertTrue(OrderResource::canEdit($order));
        $this->assertTrue(ProductVariantResource::canCreate());
        $this->assertTrue(ProductVariantResource::canEdit(ProductVariant::query()->firstOrFail()));
    }

    public function test_checkout_rejects_inactive_variant(): void
    {
        $this->seed();
        $variant = ProductVariant::query()->firstOrFail();

        $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect('/cart');

        $variant->update(['is_active' => false]);

        $this->post('/checkout', [
            'customer_name' => 'Inactive Customer',
            'customer_phone' => '0771234567',
            'delivery_address' => 'Colombo',
        ])->assertSessionHasErrors('cart');

        $this->assertDatabaseMissing('orders', ['customer_phone' => '0771234567']);
    }

    public function test_checkout_links_order_to_logged_in_customer(): void
    {
        $this->seed();
        $customer = Customer::query()->create(['phone' => '+94771234567', 'name' => 'Linked Customer']);
        $variant = ProductVariant::query()->firstOrFail();

        $this->withSession(['customer_id' => $customer->id])
            ->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertRedirect('/cart');

        $this->withSession(['customer_id' => $customer->id])
            ->post('/checkout', [
                'customer_name' => 'Linked Customer',
                'customer_phone' => '0771234567',
                'delivery_address' => 'Colombo',
            ])->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'customer_phone' => '0771234567',
            'customer_id' => $customer->id,
        ]);
    }

    private function enableSms(): void
    {
        RateLimiter::clear('order-otp-phone:+94771234567');
        RateLimiter::clear('order-otp-ip:127.0.0.1');

        config()->set('services.smslenz.enabled', true);
        config()->set('services.smslenz.base_url', 'https://smslenz.lk/api');
        config()->set('services.smslenz.user_id', '1557');
        config()->set('services.smslenz.api_key', 'testing-key');
        config()->set('services.smslenz.sender_id', 'SMSlenzDEMO');

        Setting::query()->updateOrCreate(['key' => 'sms_enabled'], ['value' => '1']);
        Setting::query()->updateOrCreate(['key' => 'sms_order_updates_enabled'], ['value' => '1']);
        Setting::query()->updateOrCreate(['key' => 'sms_sender_id'], ['value' => 'SMSlenzDEMO']);
    }

    private function placeOrder(string $name, string $phone): Order
    {
        $variant = ProductVariant::query()->firstOrFail();

        $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->post('/checkout', [
            'customer_name' => $name,
            'customer_phone' => $phone,
            'delivery_address' => 'Katunayake',
        ]);

        return Order::query()->where('customer_phone', $phone)->latest()->firstOrFail();
    }

    private function advanceOrderToPacked(Order $order, User $admin): Order
    {
        foreach (['confirmed', 'processing', 'packed'] as $status) {
            $order = app(OrderStatusService::class)->update($order->refresh(), [
                'status' => $status,
            ], $admin->id);
        }

        return $order;
    }
}
