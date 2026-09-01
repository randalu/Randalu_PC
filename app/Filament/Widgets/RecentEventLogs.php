<?php

namespace App\Filament\Widgets;

use App\Models\EventLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentEventLogs extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent event logs')
            ->description('Latest order, SMS, OTP, inventory, and settings events.')
            ->query(EventLog::query()->with(['order', 'user'])->latest())
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'error' => 'danger',
                        'warning' => 'warning',
                        'success' => 'success',
                        default => 'info',
                    }),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('summary')
                    ->wrap(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->placeholder('-'),
                TextColumn::make('customer_phone')
                    ->placeholder('-'),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->poll('60s');
    }
}
