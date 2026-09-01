<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CheckoutTest extends FeatureTestCase
{
    public function test_customer_can_place_online_order(): void
    {
        $this->seed();
        $variant = ProductVariant::query()->firstOrFail();

        $response = $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 2]);
        $response->assertRedirect('/cart');
        $token = CartItem::query()->value('cart_token');

        $checkout = $token ? $this->withCookie('cart_token', $token)->post('/checkout', [
            'customer_name' => 'Test Customer',
            'customer_phone' => '0771234567',
            'customer_email' => 'customer@example.com',
            'delivery_address' => 'Katunayake',
            'customer_notes' => 'Call before delivery',
        ]) : $this->post('/checkout', [
            'customer_name' => 'Test Customer',
            'customer_phone' => '0771234567',
            'customer_email' => 'customer@example.com',
            'delivery_address' => 'Katunayake',
            'customer_notes' => 'Call before delivery',
        ]);
        $checkout->assertRedirect();

        $this->assertDatabaseHas('orders', ['customer_phone' => '0771234567', 'status' => 'new']);
        $this->assertDatabaseHas('order_items', ['product_variant_id' => $variant->id, 'quantity' => 2]);
        $this->assertDatabaseHas('event_logs', [
            'type' => 'order.placed',
            'customer_phone' => '0771234567',
        ]);
    }

    public function test_checkout_rejects_inactive_variant(): void
    {
        $this->seed();
        $variant = ProductVariant::query()->firstOrFail();

        $response = $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1]);
        $response->assertRedirect('/cart');
        $token = CartItem::query()->value('cart_token');

        $variant->update(['is_active' => false]);

        $checkoutData = [
            'customer_name' => 'Inactive Customer',
            'customer_phone' => '0771234567',
            'delivery_address' => 'Colombo',
        ];
        $checkout = $token ? $this->withCookie('cart_token', $token)->post('/checkout', $checkoutData) : $this->post('/checkout', $checkoutData);
        $checkout->assertSessionHasErrors('cart');

        $this->assertDatabaseMissing('orders', ['customer_phone' => '0771234567']);
    }

    public function test_checkout_rejects_non_mobile_phone_numbers(): void
    {
        $this->seed();
        $variant = ProductVariant::query()->firstOrFail();

        $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1])->assertRedirect('/cart');

        $this->post('/checkout', [
            'customer_name' => 'Landline Customer',
            'customer_phone' => '0112345678',
            'delivery_address' => 'Colombo',
        ])->assertSessionHasErrors('customer_phone');

        $this->assertDatabaseMissing('orders', ['customer_name' => 'Landline Customer']);
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

    public function test_guest_cart_persists_via_cookie_token(): void
    {
        $this->seed();
        $variant = ProductVariant::query()->where('stock_quantity', '>', 0)->firstOrFail();

        $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1]);

        $this->assertDatabaseHas('cart_items', [
            'product_variant_id' => $variant->id,
            'customer_id' => null,
        ]);

        $item = CartItem::query()->firstOrFail();
        $this->assertNotEmpty($item->cart_token);
    }

    public function test_guest_cart_merges_into_customer_cart_on_login(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully'])]);

        $variant = ProductVariant::query()->where('stock_quantity', '>', 0)->firstOrFail();
        $token = Str::random(60);

        $this->withCookie('cart_token', $token)
            ->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertRedirect('/cart');

        $this->assertDatabaseHas('cart_items', ['cart_token' => $token]);

        Customer::query()->create(['phone' => '+94771234567', 'name' => 'Cart Merger']);

        $this->withCookie('cart_token', $token)
            ->post(route('customer.login.request-otp'), ['phone' => '0771234567'])
            ->assertRedirect();

        $sentOtp = null;
        Http::assertSent(function (Request $request) use (&$sentOtp): bool {
            preg_match('/\b(\d{6})\b/', (string) $request['message'], $matches);
            $sentOtp = $matches[1] ?? null;

            return $sentOtp !== null;
        });

        $this->withCookie('cart_token', $token)
            ->post(route('customer.login.verify'), [
                'phone' => '0771234567',
                'otp' => $sentOtp,
            ])->assertRedirect(route('customer.account'));

        $customer = Customer::query()->where('phone', '+94771234567')->firstOrFail();

        $this->assertDatabaseHas('cart_items', [
            'customer_id' => $customer->id,
            'product_variant_id' => $variant->id,
        ]);
    }

    public function test_checkout_includes_delivery_fee_in_total(): void
    {
        $this->seed();
        Setting::query()->updateOrCreate(['key' => 'delivery_fee'], ['value' => '1500']);
        Setting::query()->updateOrCreate(['key' => 'delivery_fee_note'], ['value' => 'Colombo delivery']);

        $variant = ProductVariant::query()->where('stock_quantity', '>', 0)->firstOrFail();
        $variant->update(['price' => 100000]);

        $response = $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1]);
        $token = CartItem::query()->value('cart_token');

        $checkoutPage = $token ? $this->withCookie('cart_token', $token)->get('/checkout') : $this->get('/checkout');
        $checkoutPage->assertOk();
        $checkoutPage->assertSee('Delivery fee');
        $checkoutPage->assertSee('1,500.00');

        $checkoutData = [
            'customer_name' => 'Delivery Fee Customer',
            'customer_phone' => '0771234567',
            'delivery_address' => 'Colombo',
        ];
        $checkout = $token ? $this->withCookie('cart_token', $token)->post('/checkout', $checkoutData) : $this->post('/checkout', $checkoutData);
        $checkout->assertRedirect();

        $order = Order::query()->where('customer_phone', '0771234567')->latest()->firstOrFail();
        $this->assertSame(1500.0, (float) $order->delivery_fee);
        $this->assertSame(101500.0, (float) $order->total);
    }

    public function test_out_of_stock_variant_rejected_on_add(): void
    {
        $this->seed();
        $variant = ProductVariant::query()->where('stock_quantity', '>', 0)->firstOrFail();
        $variant->update(['stock_quantity' => 0]);

        $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseMissing('cart_items', ['product_variant_id' => $variant->id]);
    }
}
