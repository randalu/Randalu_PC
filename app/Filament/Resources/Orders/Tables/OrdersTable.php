<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use App\Services\OrderStatusService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use RuntimeException;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->description(fn (Order $record): string => $record->created_at->diffForHumans()),
                TextColumn::make('customer_name')
                    ->searchable(),
                TextColumn::make('customer_phone')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'info',
                        'confirmed', 'processing', 'packed' => 'warning',
                        'dispatched' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->searchable(),
                TextColumn::make('subtotal')
                    ->money('LKR')
                    ->sortable(),
                TextColumn::make('delivery_fee')
                    ->money('LKR')
                    ->sortable(),
                TextColumn::make('total')
                    ->money('LKR')
                    ->sortable(),
                TextColumn::make('courier_name')
                    ->searchable(),
                TextColumn::make('tracking_number')
                    ->searchable(),
                TextColumn::make('confirmed_at')
                    ->dateTime()
                    ->sortable(),
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
                SelectFilter::make('status')
                    ->options(array_combine(Order::STATUSES, array_map(fn (string $status): string => str($status)->headline()->toString(), Order::STATUSES))),
                SelectFilter::make('payment_status')
                    ->options([
                        'cod_pending' => 'COD Pending',
                        'paid' => 'Paid',
                        'partially_paid' => 'Partially Paid',
                        'refunded' => 'Refunded',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                self::statusAction('confirm', 'confirmed', 'Confirm', 'success'),
                self::statusAction('processing', 'processing', 'Processing', 'warning'),
                self::statusAction('pack', 'packed', 'Packed', 'warning'),
                self::statusAction('dispatch', 'dispatched', 'Dispatch', 'primary', [
                    TextInput::make('courier_name')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('tracking_number')
                        ->required()
                        ->maxLength(120),
                    Textarea::make('delivery_notes')
                        ->rows(3)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ])
                    ->modalHeading('Dispatch order')
                    ->modalSubmitActionLabel('Dispatch'),
                self::statusAction('deliver', 'delivered', 'Delivered', 'success'),
                self::statusAction('cancel', 'cancelled', 'Cancel', 'danger')
                    ->requiresConfirmation(),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->poll('60s')
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @param  array<int, mixed>  $schema
     */
    private static function statusAction(string $name, string $status, string $label, string $color, array $schema = []): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->schema($schema)
            ->visible(fn (Order $record): bool => $record->canTransitionTo($status) && $record->status !== $status)
            ->action(function (Order $record, array $data) use ($status, $label): void {
                try {
                    app(OrderStatusService::class)->update($record, [
                        ...$data,
                        'status' => $status,
                    ], auth()->id());

                    Notification::make()
                        ->success()
                        ->title("Order marked {$label}")
                        ->send();
                } catch (RuntimeException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Order not updated')
                        ->body($exception->getMessage())
                        ->send();
                }
            });
    }
}
