<?php

namespace App\Filament\Resources\EventLogs\Tables;

use App\Models\EventLog;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
                    })
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('summary')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('customer_phone')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('severity')
                    ->options([
                        'info' => 'Info',
                        'success' => 'Success',
                        'warning' => 'Warning',
                        'error' => 'Error',
                    ]),
                SelectFilter::make('type')
                    ->options(fn (): array => EventLog::query()
                        ->distinct()
                        ->orderBy('type')
                        ->pluck('type', 'type')
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->poll('60s')
            ->defaultSort('created_at', 'desc');
    }
}
