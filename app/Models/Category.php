<?php

namespace App\Models;

use App\Support\CatalogCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(static function (): void {
            CatalogCache::forgetCategories();
            CatalogCache::bump();
        });

        static::deleted(static function (): void {
            CatalogCache::forgetCategories();
            CatalogCache::bump();
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
