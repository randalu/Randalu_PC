<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'index'])->name('home');
Route::get('/collections/{category:slug}', [StorefrontController::class, 'collection'])->name('collections.show');
Route::get('/products/{product:slug}', [StorefrontController::class, 'product'])->name('products.show');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/{variant}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{variant}', [CartController::class, 'remove'])->name('cart.remove');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/order-success/{order:order_number}', [CheckoutController::class, 'success'])->name('checkout.success');

Route::get('/order-status', [OrderStatusController::class, 'show'])->name('orders.status');
Route::post('/order-status/send-otp', [OrderStatusController::class, 'sendOtp'])->name('orders.status.send-otp');
Route::post('/order-status/verify', [OrderStatusController::class, 'verify'])->name('orders.status.verify');
Route::delete('/order-status', [OrderStatusController::class, 'logout'])->name('orders.status.logout');

Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');

Route::get('/login', [CustomerAuthController::class, 'show'])->name('customer.login');
Route::post('/login/request-otp', [CustomerAuthController::class, 'requestOtp'])->name('customer.login.request-otp');
Route::post('/login/verify', [CustomerAuthController::class, 'verify'])->name('customer.login.verify');
Route::get('/account', [CustomerAuthController::class, 'account'])->name('customer.account');
Route::post('/account/profile', [CustomerAuthController::class, 'profile'])->name('customer.profile');
Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');
