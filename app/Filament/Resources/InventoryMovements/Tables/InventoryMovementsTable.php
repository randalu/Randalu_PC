<?php

namespace App\Filament\Resources\InventoryMovements\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('variant.product.sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('variant.product.name')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('quantity_change')
                    ->label('Change')
                    ->badge()
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('stock_after')
                    ->label('After')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reason')
                    ->badge()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('reason')
                    ->options([
                        'manual_adjustment' => 'Manual Adjustment',
                        'order_confirmed' => 'Order Confirmed',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
