<?php

namespace App\Filament\Resources\EventLogs;

use App\Filament\Resources\EventLogs\Pages\ListEventLogs;
use App\Filament\Resources\EventLogs\Pages\ViewEventLog;
use App\Filament\Resources\EventLogs\Schemas\EventLogInfolist;
use App\Filament\Resources\EventLogs\Tables\EventLogsTable;
use App\Models\EventLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EventLogResource extends Resource
{
    protected static ?string $model = EventLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'summary';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return EventLogInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventLogs::route('/'),
            'view' => ViewEventLog::route('/{record}'),
        ];
    }
}
