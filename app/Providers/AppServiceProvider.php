<?php

namespace App\Providers;

use App\Models\Customer;
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

            $view->with('customer', $customerId ? Customer::query()->find($customerId) : null);
        });
    }
}
