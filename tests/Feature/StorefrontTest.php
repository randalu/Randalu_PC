<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CatalogCache;
use Illuminate\Support\Facades\Cache;

class StorefrontTest extends FeatureTestCase
{
    public function test_storefront_lists_seeded_products(): void
    {
        $this->seed();

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Shop Hardware')
            ->assertSee('RPC-LAP-01');
    }

    public function test_seeded_products_have_active_variants(): void
    {
        $this->seed();

        $variants = ProductVariant::query()
            ->where('is_active', true)
            ->orderBy('product_id')
            ->get();

        $this->assertNotEmpty($variants);

        foreach ($variants as $variant) {
            $this->assertNotSame('', trim($variant->size));
            $this->assertGreaterThan(0, (float) $variant->price);
        }
    }

    public function test_product_variant_selection_defaults_to_select_variant(): void
    {
        $this->seed();
        $product = ProductVariant::query()->firstOrFail()->product;

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Select variant')
            ->assertSee('Genuine hardware with warranty support.');
    }

    public function test_catalog_paginates(): void
    {
        $category = Category::query()->create(['name' => 'Paginated Hardware', 'slug' => 'paginated-hardware']);

        for ($i = 1; $i <= 15; $i++) {
            $product = Product::query()->create([
                'category_id' => $category->id,
                'sku' => 'RPC-PAG-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'name' => "Paginated Item {$i}",
                'slug' => "paginated-item-{$i}",
                'is_active' => true,
            ]);

            ProductVariant::query()->create([
                'product_id' => $product->id,
                'size' => 'Standard',
                'price' => 10000 + $i,
                'stock_quantity' => 5,
            ]);
        }

        $this->get('/')->assertOk()->assertSee('Paginated Item 1');
        $this->get('/?page=2')->assertOk();
    }

    public function test_spec_comparison_table_renders_on_product_page(): void
    {
        $product = $this->makeProduct('Comparable Monitor', 'comparable-monitor', 4);
        $product->activeVariants()->firstOrFail()->update(['size' => '27 inch']);

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('Variant / Spec')
            ->assertSee('27 inch');
    }

    public function test_out_of_stock_variant_is_disabled_on_product_page(): void
    {
        $product = $this->makeProduct('Sold Out GPU', 'sold-out-gpu', 0);

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('Out of stock');
    }

    public function test_storefront_caches_catalog_queries(): void
    {
        $this->seed();

        $this->get('/')->assertOk();
        $this->get('/')->assertOk();

        $this->assertTrue(Cache::has(CatalogCache::CATEGORIES_KEY));
        $this->assertTrue(Cache::has(CatalogCache::productsKey('', null)));
    }

    public function test_new_product_appears_after_cache_invalidation(): void
    {
        $this->seed();

        $this->get('/')->assertOk();

        $category = Category::query()->firstOrFail();

        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'RPC-CACHE-01',
            'name' => 'Cache Invalidation GPU',
            'slug' => 'cache-invalidation-gpu',
            'is_active' => true,
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'size' => '8GB',
            'price' => 99999,
            'stock_quantity' => 3,
        ]);

        $this->get('/')->assertOk()->assertSee('Cache Invalidation GPU');
    }
}
