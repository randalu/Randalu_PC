<?php

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            Product::query()->each(function (Product $product): void {
                ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->whereNotIn('size', ['90 x 90', '90 x 100'])
                    ->update(['is_active' => false]);

                foreach (['90 x 90', '90 x 100'] as $size) {
                    $existing = ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->orderByDesc('is_active')
                        ->first();

                    ProductVariant::query()->updateOrCreate([
                        'product_id' => $product->id,
                        'size' => $size,
                        'color' => 'As pictured',
                    ], [
                        'price' => $existing?->price ?? 0,
                        'stock_quantity' => $existing?->stock_quantity ?? 10,
                        'low_stock_threshold' => $existing?->low_stock_threshold ?? 2,
                        'is_active' => true,
                    ]);
                }
            });
        });
    }

    public function down(): void
    {
        ProductVariant::query()
            ->whereIn('size', ['Single', 'Double', 'Queen', 'King'])
            ->update(['is_active' => true]);
    }
};
