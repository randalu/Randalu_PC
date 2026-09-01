<?php

namespace App\Filament\Resources\EventLogs\Pages;

use App\Filament\Resources\EventLogs\EventLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEventLog extends ViewRecord
{
    protected static string $resource = EventLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
