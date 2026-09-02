<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Product;
use App\Services\OpenRouterService;
use App\Support\CatalogCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class GenerateDescriptionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 30, 60];

    public function __construct(
        public string $type,
        public int $id,
    ) {}

    public function handle(OpenRouterService $ai): void
    {
        if ($this->type === 'product_seo') {
            $product = Product::query()->find($this->id);
            if (! $product) {
                return;
            }
            $text = $ai->generateProductSeo($product);
            $product->update([
                'seo_description' => Str::limit($text, 160, ''),
                'description_generated_at' => now(),
                'ai_model' => config('services.openrouter.model'),
            ]);
            CatalogCache::bump();
        }

        if ($this->type === 'product_long') {
            $product = Product::query()->find($this->id);
            if (! $product) {
                return;
            }
            $text = $ai->generateProductLong($product);
            $product->update([
                'description' => $text,
                'description_generated_at' => now(),
                'ai_model' => config('services.openrouter.model'),
            ]);
            CatalogCache::bump();
        }

        if ($this->type === 'category') {
            $category = Category::query()->find($this->id);
            if (! $category) {
                return;
            }
            $text = $ai->generateCategoryDescription($category);
            $category->update([
                'description' => $text,
                'description_generated_at' => now(),
                'ai_model' => config('services.openrouter.model'),
            ]);
            CatalogCache::bump();
            CatalogCache::forgetCategories();
        }
    }
}
