<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class StaleNewOrders extends TableWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Stale new orders')
            ->description('New orders older than 24 hours that still need confirmation.')
            ->query(Order::query()->where('status', 'new')->where('created_at', '<=', now()->subDay())->latest())
            ->columns([
                TextColumn::make('order_number')
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->searchable(),
                TextColumn::make('customer_phone')
                    ->searchable(),
                TextColumn::make('total')
                    ->money('LKR'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->poll('60s');
    }
}
