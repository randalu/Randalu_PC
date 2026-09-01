<?php

namespace App\Filament\Widgets;

use App\Models\ProductVariant;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStockVariants extends TableWidget
{
    protected static ?int $sort = 30;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low stock variants')
            ->description('Variants at or below their configured threshold.')
            ->query(ProductVariant::query()->with('product')->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->orderBy('stock_quantity'))
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->description(fn (ProductVariant $record): ?string => $record->product?->sku)
                    ->searchable(),
                TextColumn::make('size'),
                TextColumn::make('color'),
                TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->badge()
                    ->color('danger')
                    ->sortable(),
                TextColumn::make('low_stock_threshold')
                    ->label('Threshold'),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->poll('60s');
    }
}
