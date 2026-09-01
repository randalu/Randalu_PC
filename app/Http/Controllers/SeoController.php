<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $base = rtrim(Setting::getValue('site_url', config('app.url')), '/');

        return response("User-agent: *\nAllow: /\n\nSitemap: {$base}/sitemap.xml\n", 200, ['Content-Type' => 'text/plain']);
    }

    public function sitemap(): Response
    {
        $base = rtrim(Setting::getValue('site_url', config('app.url')), '/');
        $urls = collect([route('home', absolute: false)])
            ->merge(Category::query()->where('is_active', true)->pluck('slug')->map(fn ($slug) => "/collections/{$slug}"))
            ->merge(Product::query()->where('is_active', true)->pluck('slug')->map(fn ($slug) => "/products/{$slug}"));

        $xml = view('seo.sitemap', ['urls' => $urls, 'base' => $base])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
