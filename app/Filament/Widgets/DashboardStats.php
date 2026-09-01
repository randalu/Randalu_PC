<?php

namespace App\Filament\Widgets;

use App\Models\EventLog;
use App\Models\Order;
use App\Models\ProductVariant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        return [
            Stat::make('New orders', Order::query()->where('status', 'new')->count())
                ->description('Waiting for confirmation')
                ->color('info'),
            Stat::make('Active fulfillment', Order::query()->whereIn('status', ['confirmed', 'processing', 'packed'])->count())
                ->description('Confirmed through packed')
                ->color('warning'),
            Stat::make('Dispatched today', Order::query()->where('status', 'dispatched')->whereDate('updated_at', today())->count())
                ->description('Updated to dispatched today')
                ->color('primary'),
            Stat::make('Delivered today', Order::query()->where('status', 'delivered')->whereDate('updated_at', today())->count())
                ->description('Completed today')
                ->color('success'),
            Stat::make('Stale new orders', Order::query()->where('status', 'new')->where('created_at', '<=', now()->subDay())->count())
                ->description('Older than 24 hours')
                ->color('danger'),
            Stat::make('Low stock', ProductVariant::query()->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count())
                ->description('Variants at threshold')
                ->color('danger'),
            Stat::make('Today order value', 'LKR '.number_format((float) Order::query()->whereDate('created_at', today())->sum('total'), 2))
                ->description('Orders placed today')
                ->color('success'),
            Stat::make('Events today', EventLog::query()->whereDate('created_at', today())->count())
                ->description(EventLog::query()->whereDate('created_at', today())->whereIn('severity', ['warning', 'error'])->count().' need review')
                ->color('info'),
        ];
    }
}
