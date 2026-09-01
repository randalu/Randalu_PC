<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithCart;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\EventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    use InteractsWithCart;

    public function __construct(private readonly CartService $cart) {}

    public function show(Request $request): View
    {
        $cart = $this->cart->items($this->cartCustomerId(), $this->cartToken($request));
        abort_if($cart['items']->isEmpty(), 404);

        $deliveryFee = $this->deliveryFee();

        return view('storefront.checkout', [
            'cart' => $cart,
            'delivery_fee' => $deliveryFee,
            'delivery_fee_note' => Setting::getValue('delivery_fee_note', 'Delivery fee is confirmed by our team before dispatch.'),
            'total' => $cart['subtotal'] + $deliveryFee,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cart = $this->cart->items($this->cartCustomerId(), $this->cartToken($request));
        if ($cart['items']->isEmpty()) {
            return redirect()->route('cart.show')->withErrors('Your cart is empty.');
        }

        $deliveryFee = $this->deliveryFee();

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'customer_email' => ['nullable', 'email', 'max:160'],
            'delivery_address' => ['required', 'string', 'max:1000'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = DB::transaction(function () use ($cart, $data, $deliveryFee): Order {
            $order = Order::query()->create([
                ...$data,
                'order_number' => $this->orderNumber(),
                'customer_id' => $this->cartCustomerId(),
                'subtotal' => $cart['subtotal'],
                'delivery_fee' => $deliveryFee,
                'total' => $cart['subtotal'] + $deliveryFee,
            ]);

            $subtotal = 0;

            foreach ($cart['items'] as $item) {
                // Re-validate against the current DB state — a variant may have
                // been deactivated or sold out after it was added to the cart.
                $variant = ProductVariant::query()
                    ->with('product.category')
                    ->find($item['variant']->id);

                if (! $variant
                    || ! $variant->is_active
                    || ! $variant->product?->is_active
                    || ! $variant->product?->category?->is_active
                    || $variant->stock_quantity < $item['quantity']) {
                    $sku = $variant?->product?->sku ?? $item['variant']->product?->sku ?? 'Item';

                    throw ValidationException::withMessages([
                        'cart' => "{$sku} is no longer available in the requested quantity. Please update your cart and try again.",
                    ]);
                }

                $lineTotal = $item['quantity'] * (float) $variant->price;
                $subtotal += $lineTotal;

                $order->items()->create([
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'sku' => $variant->product->sku,
                    'product_name' => $variant->product->name,
                    'size' => $variant->size,
                    'color' => $variant->color,
                    'quantity' => $item['quantity'],
                    'unit_price' => $variant->price,
                    'line_total' => $lineTotal,
                ]);
            }

            // Reflect current prices (they may have changed since the cart loaded).
            $order->update(['subtotal' => $subtotal, 'delivery_fee' => $deliveryFee, 'total' => $subtotal + $deliveryFee]);

            return $order->refresh();
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

        $this->cart->clear($this->cartCustomerId(), $this->cartToken($request));
        $this->sendOrderEmail($order->load('items'));

        return redirect()->route('checkout.success', $order)->with('status', 'Order received.');
    }

    public function success(Order $order): View
    {
        return view('storefront.success', ['order' => $order->load('items')]);
    }

    private function deliveryFee(): float
    {
        return (float) Setting::getValue('delivery_fee', '0');
    }

    private function orderNumber(): string
    {
        do {
            $number = 'RPC-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
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
            Mail::raw("New Randalu PC order {$order->order_number}\nCustomer: {$order->customer_name}\nPhone: {$order->customer_phone}\nTotal: {$order->total}", function ($message) use ($to, $order): void {
                $message->to($to)->subject("New order {$order->order_number}");
            });
        } catch (\Throwable $exception) {
            Log::warning('Order email failed', ['order' => $order->order_number, 'error' => $exception->getMessage()]);
        }
    }
}
