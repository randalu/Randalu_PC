<?php

namespace App\Filament\Resources\ProductVariants\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductVariantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Variant and Stock')
                    ->columns(2)
                    ->schema([
                        Select::make('product_id')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('LKR'),
                        Select::make('size')
                            ->options([
                                '90 x 90' => '90 x 90',
                                '90 x 100' => '90 x 100',
                            ])
                            ->required()
                            ->native(false),
                        TextInput::make('color')
                            ->required()
                            ->maxLength(100)
                            ->default('As pictured'),
                        TextInput::make('stock_quantity')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('low_stock_threshold')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(2),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                        Textarea::make('adjustment_note')
                            ->label('Stock adjustment note')
                            ->rows(3)
                            ->dehydrated(false)
                            ->helperText('Saved only when stock quantity changes.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
