<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(Order::query()->count()),
            'new' => Tab::make('New')
                ->badge(Order::query()->where('status', 'new')->count())
                ->query(fn (Builder $query): Builder => $query->where('status', 'new')),
            'active' => Tab::make('Active fulfillment')
                ->badge(Order::query()->whereIn('status', ['confirmed', 'processing', 'packed'])->count())
                ->query(fn (Builder $query): Builder => $query->whereIn('status', ['confirmed', 'processing', 'packed'])),
            'dispatched' => Tab::make('Dispatched')
                ->badge(Order::query()->where('status', 'dispatched')->count())
                ->query(fn (Builder $query): Builder => $query->where('status', 'dispatched')),
            'delivered' => Tab::make('Delivered')
                ->badge(Order::query()->where('status', 'delivered')->count())
                ->query(fn (Builder $query): Builder => $query->where('status', 'delivered')),
            'cancelled' => Tab::make('Cancelled')
                ->badge(Order::query()->where('status', 'cancelled')->count())
                ->query(fn (Builder $query): Builder => $query->where('status', 'cancelled')),
        ];
    }
}
