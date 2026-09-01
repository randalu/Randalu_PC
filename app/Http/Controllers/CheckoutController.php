<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\EventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(): View
    {
        $cart = $this->cartItems();
        abort_if($cart['items']->isEmpty(), 404);

        return view('storefront.checkout', ['cart' => $cart]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cart = $this->cartItems();
        if ($cart['items']->isEmpty()) {
            return redirect()->route('cart.show')->withErrors('Your cart is empty.');
        }

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'customer_email' => ['nullable', 'email', 'max:160'],
            'delivery_address' => ['required', 'string', 'max:1000'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = DB::transaction(function () use ($cart, $data): Order {
            $order = Order::query()->create([
                ...$data,
                'order_number' => $this->orderNumber(),
                'subtotal' => $cart['subtotal'],
                'total' => $cart['subtotal'],
            ]);

            foreach ($cart['items'] as $item) {
                $variant = $item['variant'];
                $order->items()->create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'sku' => $variant->product->sku,
                    'product_name' => $variant->product->name,
                    'size' => $variant->size,
                    'color' => $variant->color,
                    'quantity' => $item['quantity'],
                    'unit_price' => $variant->price,
                    'line_total' => $item['line_total'],
                ]);
            }

            return $order;
        });

        app(EventLogger::class)->record(
            type: 'order.placed',
            summary: "Order {$order->order_number} placed",
            subject: $order,
            order: $order,
            customerPhone: $order->customer_phone,
            ipAddress: $request->ip(),
            metadata: [
                'total' => (float) $order->total,
                'items' => $order->items()->count(),
            ],
        );

        session()->forget('cart');
        $this->sendOrderEmail($order->load('items'));

        return redirect()->route('checkout.success', $order)->with('status', 'Order received.');
    }

    public function success(Order $order): View
    {
        return view('storefront.success', ['order' => $order->load('items')]);
    }

    private function cartItems(): array
    {
        $cart = session('cart', []);
        if ($cart === []) {
            return ['items' => collect(), 'subtotal' => 0];
        }

        $variants = ProductVariant::query()->with('product.category')->whereIn('id', array_keys($cart))->get();
        $items = $variants->map(function (ProductVariant $variant) use ($cart) {
            $quantity = (int) $cart[$variant->id];

            return ['variant' => $variant, 'quantity' => $quantity, 'line_total' => $quantity * (float) $variant->price];
        });

        return ['items' => $items, 'subtotal' => $items->sum('line_total')];
    }

    private function orderNumber(): string
    {
        do {
            $number = 'PMS-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }

    private function sendOrderEmail(Order $order): void
    {
        $to = Setting::getValue('admin_email', config('mail.from.address'));
        if (! $to) {
            return;
        }

        try {
            Mail::raw("New PMS order {$order->order_number}\nCustomer: {$order->customer_name}\nPhone: {$order->customer_phone}\nTotal: {$order->total}", function ($message) use ($to, $order): void {
                $message->to($to)->subject("New order {$order->order_number}");
            });
        } catch (\Throwable $exception) {
            Log::warning('Order email failed', ['order' => $order->order_number, 'error' => $exception->getMessage()]);
        }
    }
}
