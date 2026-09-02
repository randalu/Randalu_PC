<?php

namespace App\Models;

use App\Models\Concerns\InvalidatesCatalogCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use InvalidatesCatalogCache;

    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'slug',
        'image_path',
        'seo_description',
        'description',
        'specs',
        'sort_order',
        'is_active',
        'description_generated_at',
        'ai_model',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'specs' => 'array',
            'description_generated_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true)->orderBy('price')->orderBy('size');
    }
}
