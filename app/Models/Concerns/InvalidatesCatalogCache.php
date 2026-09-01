<?php

namespace App\Models\Concerns;

use App\Support\CatalogCache;

/**
 * Bump the catalog cache version whenever a catalog entity is saved or deleted,
 * so storefront listings pick up changes within the listing TTL.
 */
trait InvalidatesCatalogCache
{
    protected static function bootInvalidatesCatalogCache(): void
    {
        static::saved(static function (): void {
            CatalogCache::bump();
        });

        static::deleted(static function (): void {
            CatalogCache::bump();
        });
    }
}
