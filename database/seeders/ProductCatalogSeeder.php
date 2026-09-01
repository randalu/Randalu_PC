<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $collections = [
            'Earth & Clay Collection' => 'Warm earth-toned bedsheet sets with floral and rustic everyday patterns.',
            'Scandinavian Soft Collection' => 'Soft modern patterns inspired by cool tones, florals, and minimal prints.',
            'Kids & Teen Collection' => 'Playful, bright, and gentle bedsheet sets for younger bedrooms.',
            'Urban Edge Collection' => 'Bold modern prints for statement bedroom styling.',
            'Luxury Street Collection' => 'High-contrast and premium-inspired bedsheet designs.',
            'Orient Luxe Collection' => 'Rich decorative bedding with refined traditional character.',
            'Luxury Minimal Collection' => 'Clean, calm, and polished bedding designs for minimal rooms.',
            'Urban Safari Collection' => 'Graphic safari-inspired patterns with contemporary contrast.',
            'Romantic Bloom Collection' => 'Soft floral bedding designs with romantic color palettes.',
        ];

        $categoryIds = [];
        $sort = 1;
        foreach ($collections as $name => $description) {
            $category = Category::query()->updateOrCreate([
                'slug' => Str::slug($name),
            ], [
                'name' => $name,
                'description' => $description,
                'sort_order' => $sort++,
                'is_active' => true,
            ]);
            $categoryIds[$name] = $category->id;
        }

        $products = [
            ['Earth & Clay Collection', 'EC-NEM-01', 'Nordic Ember Bedsheet Set', 'images/24087.png'],
            ['Earth & Clay Collection', 'EC-SLB-02', 'Slate Blossom Bedsheet Set', 'images/24084.png'],
            ['Earth & Clay Collection', 'EC-CBL-04', 'Cinnamon Bloom Bedsheet Set', 'images/24083.png'],
            ['Earth & Clay Collection', 'EC-BLF-09', 'Beautiful Life Bedsheet Set', 'images/24068.png'],
            ['Earth & Clay Collection', 'EC-TMR-14', 'Terracotta Motif Rust Bedsheet Set', 'images/24075.png'],
            ['Scandinavian Soft Collection', 'SS-STN-03', 'Stardust Night Bedsheet Set', 'images/24065.png'],
            ['Scandinavian Soft Collection', 'SS-ABM-07', 'Arctic Blue Mosaic Bedsheet Set', 'images/24066.png'],
            ['Scandinavian Soft Collection', 'SS-LWB-16', 'Lavender Wild Bloom Bedsheet Set', 'images/24077.png'],
            ['Scandinavian Soft Collection', 'SS-LVM-19', 'Lavender Mist Motif Bedsheet Set', 'images/24080.png'],
            ['Scandinavian Soft Collection', 'SS-MTL-23', 'Minimal Text Linen Bedsheet Set', 'images/11058.png'],
            ['Kids & Teen Collection', 'KT-SLH-05', 'Sweetheart Loop Bedsheet Set', 'images/24082.png'],
            ['Kids & Teen Collection', 'KT-OCS-06', 'Ocean Serenity Bedsheet Set', 'images/24081.png'],
            ['Kids & Teen Collection', 'KT-SDG-10', 'Sunny Daisy Garden Bedsheet Set', 'images/24069.png'],
            ['Kids & Teen Collection', 'KT-GAP-20', 'Gentle Apple Dream Bedsheet Set', 'images/11129.png'],
            ['Kids & Teen Collection', 'KT-CRP-22', 'Candy Rose Patchwork Bedsheet Set', 'images/11125.png'],
            ['Urban Edge Collection', 'UE-IFR-08', 'Inferno Rush Bedsheet Set', 'images/24067.png'],
            ['Luxury Street Collection', 'LS-CRN-11', 'Crimson Noir Bedsheet Set', 'images/24070.png'],
            ['Luxury Street Collection', 'LS-MGD-13', 'Monaco Gold Bedsheet Set', 'images/24074.png'],
            ['Luxury Street Collection', 'LS-BCT-26', 'British Camel Tartan Bedsheet Set', 'images/11054.png'],
            ['Orient Luxe Collection', 'OL-GDH-12', 'Golden Harmony Bedsheet Set', 'images/24073.png'],
            ['Luxury Minimal Collection', 'LM-CRB-15', 'Coral Blush Bedsheet Set', 'images/24079.png'],
            ['Luxury Minimal Collection', 'LM-SGH-24', 'Silver Grid Harmony Bedsheet Set', 'images/11057.png'],
            ['Luxury Minimal Collection', 'LM-TWH-25', 'Teal Weave Harmony Bedsheet Set', 'images/11056.png'],
            ['Luxury Minimal Collection', 'LM-STN-27', 'Sandstone Herringbone Bedsheet Set', 'images/11053.png'],
            ['Urban Safari Collection', 'US-SVN-17', 'Savannah Noir Bedsheet Set', 'images/24078.png'],
            ['Romantic Bloom Collection', 'RB-RGD-18', 'Rose Garden Dream Bedsheet Set', 'images/24076.png'],
            ['Romantic Bloom Collection', 'RB-FWS-21', 'Forever Wishes Bedsheet Set', 'images/11128.png'],
        ];

        $productSort = 1;
        foreach ($products as [$collection, $sku, $name, $image]) {
            $cleanName = Str::of($name)->replace(' Bedsheet Set', '');
            $product = Product::query()->updateOrCreate([
                'sku' => $sku,
            ], [
                'category_id' => $categoryIds[$collection],
                'name' => $name,
                'slug' => Str::slug($sku.' '.$name),
                'image_path' => $image,
                'seo_description' => "{$cleanName} is a locally tailored bedsheet and pillowcase set from Priyanthi Multi Stores in the {$collection}.",
                'sort_order' => $productSort++,
                'is_active' => true,
            ]);

            $product->variants()
                ->whereNotIn('size', ['90 x 90', '90 x 100'])
                ->update(['is_active' => false]);

            foreach (['90 x 90', '90 x 100'] as $size) {
                $product->variants()->updateOrCreate([
                    'size' => $size,
                    'color' => 'As pictured',
                ], [
                    'price' => 0,
                    'stock_quantity' => 10,
                    'low_stock_threshold' => 2,
                    'is_active' => true,
                ]);
            }
        }

        $settings = [
            'site_url' => 'https://bedsheets.ptree.lk',
            'store_name' => 'Priyanthi Multi Stores',
            'store_phone' => '+94776474542',
            'whatsapp_number' => '94776474542',
            'store_address' => 'Priyanthi Multi Stores, Katunayake, Sri Lanka',
            'google_maps_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3958.770553911933!2d79.87817187499869!3d7.1525068928518865!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae2f11dde391c3b%3A0x18d0e58c6ffb9ba3!2sPriyanthi%20Multi%20Stores!5e0!3m2!1sen!2slk!4v1778339907048!5m2!1sen!2slk',
            'admin_email' => env('ADMIN_EMAIL', 'admin@bedsheets.ptree.lk'),
            'currency' => 'LKR',
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
