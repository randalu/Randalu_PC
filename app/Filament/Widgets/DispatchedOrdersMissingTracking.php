<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class DispatchedOrdersMissingTracking extends TableWidget
{
    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Dispatched orders missing tracking')
            ->description('Dispatched orders should include courier and tracking details for customers.')
            ->query(
                Order::query()
                    ->where('status', 'dispatched')
                    ->where(fn ($query) => $query
                        ->whereNull('courier_name')
                        ->orWhereNull('tracking_number')
                        ->orWhere('courier_name', '')
                        ->orWhere('tracking_number', ''))
                    ->latest('updated_at')
            )
            ->columns([
                TextColumn::make('order_number')
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->searchable(),
                TextColumn::make('customer_phone')
                    ->searchable(),
                TextColumn::make('courier_name')
                    ->placeholder('-'),
                TextColumn::make('tracking_number')
                    ->placeholder('-'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->poll('60s');
    }
}
