<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Support\CatalogCache;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StorefrontController extends Controller
{
    public function index(Request $request): View
    {
        $categories = $this->cachedCategories();

        $search = trim((string) $request->query('s'));

        $products = $this->cachedProducts($search, null, function () use ($search): Collection {
            $query = Product::query()
                ->with(['category', 'activeVariants'])
                ->where('is_active', true)
                ->whereHas('category', fn ($query) => $query->where('is_active', true));

            if ($search !== '') {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"));
                });
            }

            return $query->orderBy('sort_order')->get();
        });

        return view('storefront.index', [
            'categories' => $categories,
            'products' => $this->paginate($products, 12, $request),
            'search' => $search,
            'settings' => $this->settings(),
        ]);
    }

    private function cachedCategories(): Collection
    {
        $value = Cache::get(CatalogCache::CATEGORIES_KEY);
        if ($value instanceof Collection) {
            return $value;
        }
        if ($value !== null) {
            Cache::forget(CatalogCache::CATEGORIES_KEY);
        }

        return Cache::rememberForever(CatalogCache::CATEGORIES_KEY, function (): Collection {
            return Category::query()->where('is_active', true)->orderBy('sort_order')->get();
        });
    }

    private function cachedProducts(?string $search, ?int $categoryId, callable $callback): Collection
    {
        $key = CatalogCache::productsKey($search, $categoryId);
        $value = Cache::get($key);
        if ($value instanceof Collection) {
            return $value;
        }
        if ($value !== null) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addMinutes(CatalogCache::PRODUCT_TTL_MINUTES), $callback);
    }

    public function collection(Category $category, Request $request): View
    {
        abort_unless($category->is_active, 404);

        $products = $this->cachedProducts(null, $category->id, fn (): Collection => $category->products()->with('activeVariants')->where('is_active', true)->orderBy('sort_order')->get());

        $paginated = $this->paginate($products, 12, $request);
        // Prevent N+1 queries when accessing $product->category in the view
        // by injecting the already loaded parent category model.
        $paginated->getCollection()->each->setRelation('category', $category);

        return view('storefront.collection', [
            'category' => $category,
            'products' => $paginated,
            'categories' => $this->cachedCategories(),
            'settings' => $this->settings(),
        ]);
    }

    public function product(Product $product): View
    {
        abort_unless($product->is_active && $product->category?->is_active, 404);

        return view('storefront.product', [
            'product' => $product->load(['category', 'activeVariants']),
            'settings' => $this->settings(),
        ]);
    }

    private function paginate(Collection $items, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $lastPage = max(1, (int) ceil($items->count() / $perPage));

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            min($page, $lastPage),
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ],
        );
    }

    private function settings(): array
    {
        return [
            'store_name' => Setting::getValue('store_name', 'Randalu PC'),
            'store_phone' => Setting::getValue('store_phone', '+94776474542'),
            'whatsapp_number' => Setting::getValue('whatsapp_number', '94776474542'),
            'store_address' => Setting::getValue('store_address', 'Randalu PC, Sri Lanka'),
            'google_maps_embed_url' => Setting::getValue('google_maps_embed_url'),
            'currency' => Setting::getValue('currency', 'LKR'),
        ];
    }
}
