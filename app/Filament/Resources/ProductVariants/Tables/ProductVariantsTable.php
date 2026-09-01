<?php

namespace App\Filament\Resources\ProductVariants\Tables;

use App\Models\ProductVariant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductVariantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->description(fn (ProductVariant $record): ?string => $record->product?->sku)
                    ->searchable(),
                TextColumn::make('size')
                    ->searchable(),
                TextColumn::make('color')
                    ->searchable(),
                TextColumn::make('price')
                    ->money('LKR')
                    ->sortable(),
                TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->badge()
                    ->color(fn (ProductVariant $record): string => $record->isLowStock() ? 'danger' : 'success')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('low_stock_threshold')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
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
                SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
