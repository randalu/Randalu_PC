<?php

namespace App\Providers;

use App\Models\Customer;
use App\Services\CartService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share the signed-in storefront customer with every view.
        View::composer('*', function ($view): void {
            $customerId = session('customer_id');
            $customerId = is_numeric($customerId) ? (int) $customerId : null;

            $view->with('customer', $customerId ? Customer::query()->find($customerId) : null);
        });

        // Share the cart item count with the storefront layout header.
        View::composer('layouts.storefront', function ($view): void {
            $customerId = session('customer_id');

            $view->with('cart_count', app(CartService::class)->count(
                is_numeric($customerId) ? (int) $customerId : null,
                request()->cookie('cart_token'),
            ));
        });
    }
}
