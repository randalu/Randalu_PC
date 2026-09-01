<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function enableSms(): void
    {
        RateLimiter::clear('order-otp-phone:+94771234567');
        RateLimiter::clear('order-otp-ip:127.0.0.1');
        RateLimiter::clear('customer-otp-phone:+94771234567');
        RateLimiter::clear('customer-otp-ip:127.0.0.1');

        config()->set('services.smslenz.enabled', true);
        config()->set('services.smslenz.base_url', 'https://smslenz.lk/api');
        config()->set('services.smslenz.user_id', '1557');
        config()->set('services.smslenz.api_key', 'testing-key');
        config()->set('services.smslenz.sender_id', 'SMSlenzDEMO');

        Setting::query()->updateOrCreate(['key' => 'sms_enabled'], ['value' => '1']);
        Setting::query()->updateOrCreate(['key' => 'sms_order_updates_enabled'], ['value' => '1']);
        Setting::query()->updateOrCreate(['key' => 'sms_sender_id'], ['value' => 'SMSlenzDEMO']);
    }

    protected function placeOrder(string $name, string $phone): Order
    {
        $variant = ProductVariant::query()->firstOrFail();

        $this->post('/cart', ['variant_id' => $variant->id, 'quantity' => 1]);
        // Cart token is stored plain in DB (cookie is encrypted) - use plain for withCookie
        $token = CartItem::query()->value('cart_token');

        $checkoutData = [
            'customer_name' => $name,
            'customer_phone' => $phone,
            'delivery_address' => 'Katunayake',
        ];

        if ($token !== null) {
            $this->withCookie('cart_token', $token)->post('/checkout', $checkoutData);
        } else {
            $this->post('/checkout', $checkoutData);
        }

        return Order::query()->where('customer_phone', $phone)->latest()->firstOrFail();
    }

    protected function advanceOrderToPacked(Order $order, User $admin): Order
    {
        foreach (['confirmed', 'processing', 'packed'] as $status) {
            $order = app(OrderStatusService::class)->update($order->refresh(), [
                'status' => $status,
            ], $admin->id);
        }

        return $order;
    }

    protected function makeProduct(string $name, string $slug, int $stock): Product
    {
        $category = Category::query()->create([
            'name' => $name.' Category',
            'slug' => $slug.'-category',
        ]);

        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'RPC-'.strtoupper($slug),
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'size' => 'Standard',
            'price' => 10000,
            'stock_quantity' => $stock,
        ]);

        return $product;
    }
}
