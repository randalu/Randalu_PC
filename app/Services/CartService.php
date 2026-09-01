<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CartService
{
    /**
     * @return array{items: \Illuminate\Support\Collection<int, array{variant: ProductVariant, quantity: int, line_total: float}>, subtotal: float}
     */
    public function items(?int $customerId, ?string $token): array
    {
        $query = $this->baseQuery($customerId, $token);

        if ($query === null) {
            return ['items' => collect(), 'subtotal' => 0];
        }

        $items = $query->with('variant.product.category')->get()->map(fn (CartItem $row): array => [
            'variant' => $row->variant,
            'quantity' => $row->quantity,
            'line_total' => $row->quantity * (float) $row->variant->price,
        ]);

        return ['items' => $items, 'subtotal' => $items->sum('line_total')];
    }

    public function count(?int $customerId, ?string $token): int
    {
        $query = $this->baseQuery($customerId, $token);

        return $query?->count() ?? 0;
    }

    public function add(int $variantId, int $quantity, ?int $customerId, ?string $token): void
    {
        $variant = ProductVariant::query()->with('product')->findOrFail($variantId);

        abort_unless($variant->is_active && $variant->product->is_active, 404);

        $row = $this->row($customerId, $token, $variantId);
        $current = $row?->quantity ?? 0;
        $available = $variant->stock_quantity;

        if ($available < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'This item is currently out of stock.',
            ]);
        }

        $newQuantity = min(99, min($available, $current + $quantity));

        if ($row) {
            $row->update(['quantity' => $newQuantity]);
        } else {
            CartItem::query()->create([
                'customer_id' => $customerId,
                'cart_token' => $customerId === null ? $token : null,
                'product_variant_id' => $variantId,
                'quantity' => $newQuantity,
            ]);
        }
    }

    public function update(int $variantId, int $quantity, ?int $customerId, ?string $token): void
    {
        $variant = ProductVariant::query()->findOrFail($variantId);
        $row = $this->row($customerId, $token, $variantId);

        if (! $row) {
            return;
        }

        if ($variant->stock_quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$variant->stock_quantity} available in stock.",
            ]);
        }

        $row->update(['quantity' => min(99, $quantity)]);
    }

    public function remove(int $variantId, ?int $customerId, ?string $token): void
    {
        $this->row($customerId, $token, $variantId)?->delete();
    }

    public function clear(?int $customerId, ?string $token): void
    {
        $this->baseQuery($customerId, $token)?->delete();
    }

    /**
     * Move a guest cart (token) into the signed-in customer's cart.
     */
    public function mergeGuestCart(?string $token, int $customerId): void
    {
        if (! $token) {
            return;
        }

        CartItem::query()->where('cart_token', $token)->get()->each(function (CartItem $row) use ($customerId): void {
            $existing = CartItem::query()
                ->where('customer_id', $customerId)
                ->where('product_variant_id', $row->product_variant_id)
                ->first();

            if ($existing) {
                $existing->update(['quantity' => min(99, $existing->quantity + $row->quantity)]);
                $row->delete();
            } else {
                $row->update(['customer_id' => $customerId, 'cart_token' => null]);
            }
        });
    }

    private function row(?int $customerId, ?string $token, int $variantId): ?CartItem
    {
        return $this->baseQuery($customerId, $token)?->where('product_variant_id', $variantId)->first();
    }

    private function baseQuery(?int $customerId, ?string $token): ?Builder
    {
        if ($customerId !== null) {
            return CartItem::query()->where('customer_id', $customerId);
        }

        if ($token !== null && $token !== '') {
            return CartItem::query()->where('cart_token', $token);
        }

        return null;
    }
}
