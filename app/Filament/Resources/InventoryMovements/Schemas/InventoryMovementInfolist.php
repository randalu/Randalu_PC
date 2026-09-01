<?php

namespace App\Filament\Resources\InventoryMovements\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InventoryMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('variant.product.sku')
                    ->label('SKU'),
                TextEntry::make('variant.product.name')
                    ->label('Product'),
                TextEntry::make('order.order_number')
                    ->label('Order')
                    ->placeholder('-'),
                TextEntry::make('quantity_change')
                    ->numeric(),
                TextEntry::make('stock_after')
                    ->numeric(),
                TextEntry::make('reason'),
                TextEntry::make('note')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('user.name')
                    ->label('User')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
