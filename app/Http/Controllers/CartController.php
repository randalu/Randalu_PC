<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(): View
    {
        return view('storefront.cart', ['cart' => $this->cartItems()]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $variant = ProductVariant::query()->with('product')->findOrFail($data['variant_id']);
        abort_unless($variant->is_active && $variant->product->is_active, 404);

        $cart = session('cart', []);
        $cart[$variant->id] = min(99, ($cart[$variant->id] ?? 0) + (int) $data['quantity']);
        session(['cart' => $cart]);

        return redirect()->route('cart.show')->with('status', 'Added to cart.');
    }

    public function update(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);
        $cart = session('cart', []);
        $cart[$variant->id] = (int) $data['quantity'];
        session(['cart' => $cart]);

        return back()->with('status', 'Cart updated.');
    }

    public function remove(ProductVariant $variant): RedirectResponse
    {
        $cart = session('cart', []);
        unset($cart[$variant->id]);
        session(['cart' => $cart]);

        return back()->with('status', 'Item removed.');
    }

    private function cartItems(): array
    {
        $cart = session('cart', []);
        if ($cart === []) {
            return ['items' => collect(), 'subtotal' => 0];
        }

        $variants = ProductVariant::query()
            ->with('product.category')
            ->whereIn('id', array_keys($cart))
            ->get();

        $items = $variants->map(function (ProductVariant $variant) use ($cart) {
            $quantity = (int) $cart[$variant->id];

            return [
                'variant' => $variant,
                'quantity' => $quantity,
                'line_total' => $quantity * (float) $variant->price,
            ];
        });

        return ['items' => $items, 'subtotal' => $items->sum('line_total')];
    }
}
