<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->columns(2)
                    ->schema([
                        TextInput::make('order_number')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('customer_name')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('customer_phone')
                            ->tel()
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('customer_email')
                            ->email()
                            ->disabled()
                            ->dehydrated(false),
                        Textarea::make('delivery_address')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Textarea::make('customer_notes')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
                Section::make('Fulfillment')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options(array_combine(Order::STATUSES, array_map(fn (string $status): string => str($status)->headline()->toString(), Order::STATUSES)))
                            ->required(),
                        Select::make('payment_status')
                            ->options([
                                'cod_pending' => 'COD Pending',
                                'paid' => 'Paid',
                                'partially_paid' => 'Partially Paid',
                                'refunded' => 'Refunded',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),
                        TextInput::make('delivery_fee')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('LKR'),
                        TextInput::make('courier_name')
                            ->maxLength(120),
                        TextInput::make('tracking_number')
                            ->maxLength(120),
                        DateTimePicker::make('confirmed_at')
                            ->disabled()
                            ->dehydrated(false),
                        Textarea::make('delivery_notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
