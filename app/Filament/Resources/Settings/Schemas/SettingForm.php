<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Setting')
                    ->schema([
                        TextInput::make('key')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('SMS settings use keys that start with sms_. API secrets stay in .env.')
                            ->maxLength(255),
                        Textarea::make('value')
                            ->helperText('For sms_enabled and sms_order_updates_enabled, use 1 for enabled and 0 for disabled.')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
