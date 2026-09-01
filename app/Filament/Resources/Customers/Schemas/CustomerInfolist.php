<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->placeholder('-'),
                        TextEntry::make('phone'),
                        TextEntry::make('email')
                            ->placeholder('-'),
                        TextEntry::make('delivery_address')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
                Section::make('Orders')
                    ->schema([
                        RepeatableEntry::make('orders')
                            ->hiddenLabel()
                            ->columns(5)
                            ->schema([
                                TextEntry::make('order_number')
                                    ->label('Order'),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('payment_status')
                                    ->badge(),
                                TextEntry::make('total')
                                    ->money('LKR'),
                                TextEntry::make('created_at')
                                    ->dateTime(),
                            ]),
                    ]),
            ]);
    }
}
