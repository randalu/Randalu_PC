<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function updateVariant(ProductVariant $variant, array $data, ?int $userId = null, ?string $note = null): ProductVariant
    {
        $result = DB::transaction(function () use ($variant, $data, $userId, $note): array {
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);
            $oldStock = $variant->stock_quantity;

            $variant->update($data);
            $variant->refresh();

            $change = $variant->stock_quantity - $oldStock;

            if ($change !== 0) {
                InventoryMovement::query()->create([
                    'product_variant_id' => $variant->id,
                    'quantity_change' => $change,
                    'stock_after' => $variant->stock_quantity,
                    'reason' => 'manual_adjustment',
                    'note' => $note,
                    'user_id' => $userId,
                ]);
            }

            return [
                'variant' => $variant,
                'old_stock' => $oldStock,
                'change' => $change,
            ];
        });

        /** @var ProductVariant $updatedVariant */
        $updatedVariant = $result['variant'];

        if ($result['change'] !== 0) {
            app(EventLogger::class)->record(
                type: 'inventory.adjusted',
                summary: "Inventory adjusted for {$updatedVariant->product?->sku} {$updatedVariant->size}",
                subject: $updatedVariant,
                userId: $userId,
                metadata: [
                    'old_stock' => $result['old_stock'],
                    'new_stock' => $updatedVariant->stock_quantity,
                    'change' => $result['change'],
                    'note' => $note,
                ],
            );
        }

        return $updatedVariant;
    }
}
