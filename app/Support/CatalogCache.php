<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cache keys and helpers for the storefront catalog cache.
 *
 * Product listings are cached for a short TTL and keyed by a global "version"
 * that is bumped whenever catalog data changes (product/variant/stock saves).
 * Old keys are orphaned but expire within the TTL, so no key-sweeping is needed.
 */
final class CatalogCache
{
    public const CATEGORIES_KEY = 'catalog.categories';

    public const VERSION_KEY = 'catalog.version';

    public const PRODUCT_TTL_MINUTES = 5;

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    public static function forgetCategories(): void
    {
        Cache::forget(self::CATEGORIES_KEY);
    }

    public static function productsKey(?string $search, ?int $categoryId): string
    {
        $scope = ($search ?? '').'|'.($categoryId ?? 'index');

        return 'catalog.products.v'.self::version().'.'.md5($scope);
    }
}
