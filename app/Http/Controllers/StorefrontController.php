<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()
            ->with(['category', 'activeVariants'])
            ->where('is_active', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true));

        if ($search = trim((string) $request->query('s'))) {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"));
            });
        }

        return view('storefront.index', [
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'products' => $query->orderBy('sort_order')->get(),
            'search' => $search ?? '',
            'settings' => $this->settings(),
        ]);
    }

    public function collection(Category $category): View
    {
        abort_unless($category->is_active, 404);

        $products = $category->products()->with('activeVariants')->where('is_active', true)->orderBy('sort_order')->get();
        // ⚡ Bolt: Prevent N+1 queries when accessing $product->category in the view
        // by injecting the already loaded parent category model.
        $products->each->setRelation('category', $category);

        return view('storefront.collection', [
            'category' => $category,
            'products' => $products,
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->get(),
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

    private function settings(): array
    {
        return [
            'store_name' => Setting::getValue('store_name', 'Priyanthi Multi Stores'),
            'store_phone' => Setting::getValue('store_phone', '+94776474542'),
            'whatsapp_number' => Setting::getValue('whatsapp_number', '94776474542'),
            'store_address' => Setting::getValue('store_address', 'Priyanthi Multi Stores, Katunayake, Sri Lanka'),
            'google_maps_embed_url' => Setting::getValue('google_maps_embed_url'),
            'currency' => Setting::getValue('currency', 'LKR'),
        ];
    }
}
