<?php

namespace App\Filament\Resources\EventLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EventLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime(),
                        TextEntry::make('severity')
                            ->badge(),
                        TextEntry::make('type')
                            ->badge(),
                        TextEntry::make('summary')
                            ->columnSpanFull(),
                    ]),
                Section::make('Context')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('order.order_number')
                            ->label('Order')
                            ->placeholder('-'),
                        TextEntry::make('user.name')
                            ->label('User')
                            ->placeholder('-'),
                        TextEntry::make('customer_phone')
                            ->placeholder('-'),
                        TextEntry::make('ip_address')
                            ->label('IP address')
                            ->placeholder('-'),
                        TextEntry::make('subject_type')
                            ->placeholder('-'),
                        TextEntry::make('subject_id')
                            ->placeholder('-'),
                    ]),
                Section::make('Metadata')
                    ->schema([
                        TextEntry::make('metadata')
                            ->hiddenLabel()
                            ->formatStateUsing(fn ($state): string => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '-')
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
