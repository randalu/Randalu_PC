<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order_number'),
                        TextEntry::make('customer_name'),
                        TextEntry::make('customer_phone'),
                        TextEntry::make('customer_email')
                            ->placeholder('-'),
                        TextEntry::make('delivery_address')
                            ->columnSpanFull(),
                        TextEntry::make('customer_notes')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(5)
                            ->schema([
                                TextEntry::make('sku')
                                    ->label('SKU'),
                                TextEntry::make('product_name')
                                    ->label('Product'),
                                TextEntry::make('size'),
                                TextEntry::make('color'),
                                TextEntry::make('quantity')
                                    ->numeric(),
                                TextEntry::make('unit_price')
                                    ->money('LKR'),
                                TextEntry::make('line_total')
                                    ->money('LKR'),
                            ]),
                    ]),
                Section::make('Fulfillment')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('payment_status')
                            ->badge(),
                        TextEntry::make('subtotal')
                            ->money('LKR'),
                        TextEntry::make('delivery_fee')
                            ->money('LKR'),
                        TextEntry::make('total')
                            ->money('LKR'),
                        TextEntry::make('courier_name')
                            ->placeholder('-'),
                        TextEntry::make('tracking_number')
                            ->placeholder('-'),
                        TextEntry::make('confirmed_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('delivery_notes')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Order history')
                    ->schema([
                        RepeatableEntry::make('events')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->dateTime()
                                    ->label('When'),
                                TextEntry::make('type')
                                    ->badge(),
                                TextEntry::make('severity')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'error' => 'danger',
                                        'warning' => 'warning',
                                        'success' => 'success',
                                        default => 'info',
                                    }),
                                TextEntry::make('user.name')
                                    ->label('User')
                                    ->placeholder('-'),
                                TextEntry::make('summary')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
