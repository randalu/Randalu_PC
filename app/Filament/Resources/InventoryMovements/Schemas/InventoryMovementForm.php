<?php

namespace App\Filament\Resources\InventoryMovements\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InventoryMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('product_variant_id')
                    ->required()
                    ->numeric(),
                Select::make('order_id')
                    ->relationship('order', 'id'),
                TextInput::make('quantity_change')
                    ->required()
                    ->numeric(),
                TextInput::make('stock_after')
                    ->required()
                    ->numeric(),
                TextInput::make('reason')
                    ->required(),
                Textarea::make('note')
                    ->columnSpanFull(),
                TextInput::make('user_id')
                    ->numeric(),
            ]);
    }
}
