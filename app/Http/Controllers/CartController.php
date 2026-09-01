<?php

namespace App\Http\Controllers;

use App\Http\Concerns\InteractsWithCart;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    use InteractsWithCart;

    public function __construct(private readonly CartService $cart) {}

    public function show(Request $request): View
    {
        return view('storefront.cart', [
            'cart' => $this->cart->items($this->cartCustomerId(), $this->cartToken($request)),
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $this->cart->add(
            (int) $data['variant_id'],
            (int) $data['quantity'],
            $this->cartCustomerId(),
            $this->cartCustomerId() === null ? $this->ensureCartToken($request) : null,
        );

        return redirect()->route('cart.show')->with('status', 'Added to cart.');
    }

    public function update(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);

        $this->cart->update(
            $variant->id,
            (int) $data['quantity'],
            $this->cartCustomerId(),
            $this->cartToken($request),
        );

        return back()->with('status', 'Cart updated.');
    }

    public function remove(Request $request, ProductVariant $variant): RedirectResponse
    {
        $this->cart->remove($variant->id, $this->cartCustomerId(), $this->cartToken($request));

        return back()->with('status', 'Item removed.');
    }
}
