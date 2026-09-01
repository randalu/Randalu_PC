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
            'Laptops & Notebooks' => 'Business laptops and ultrabooks for work, study, and everyday use.',
            'Desktop PCs' => 'Ready-built gaming towers and compact office desktops.',
            'Processors (CPUs)' => 'Intel and AMD processors for new builds and upgrades.',
            'Motherboards' => 'ATX and Micro-ATX boards for Intel and AMD platforms.',
            'Graphics Cards' => 'NVIDIA and AMD GPUs for gaming and content creation.',
            'Memory (RAM)' => 'DDR4 and DDR5 memory kits for laptops and desktops.',
            'Storage (SSD & HDD)' => 'NVMe and SATA solid-state drives for faster systems.',
            'Power Supplies (PSUs)' => 'Reliable 80+ certified power supplies for every build.',
            'Monitors & Displays' => 'FHD and QHD monitors for work and gaming setups.',
            'Peripherals & Accessories' => 'Keyboards, mice, and headsets for complete setups.',
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

        $productImages = [
            'RPC-LAP-01' => 'images/products/rpc-lap-01.jpg',
            'RPC-LAP-02' => 'images/products/rpc-lap-02.jpg',
            'RPC-DSK-01' => 'images/products/rpc-dsk-01.jpg',
            'RPC-DSK-02' => 'images/products/rpc-dsk-02.jpg',
            'RPC-CPU-01' => 'images/products/rpc-cpu-01.jpg',
            'RPC-CPU-02' => 'images/products/rpc-cpu-02.jpg',
            'RPC-MBD-01' => 'images/products/rpc-mbd-01.jpg',
            'RPC-MBD-02' => 'images/products/rpc-mbd-02.jpg',
            'RPC-GPU-01' => 'images/products/rpc-gpu-01.jpg',
            'RPC-GPU-02' => 'images/products/rpc-gpu-02.jpg',
            'RPC-RAM-01' => 'images/products/rpc-ram-01.jpg',
            'RPC-RAM-02' => 'images/products/rpc-ram-02.jpg',
            'RPC-SSD-01' => 'images/products/rpc-ssd-01.jpg',
            'RPC-SSD-02' => 'images/products/rpc-ssd-02.jpg',
            'RPC-PSU-01' => 'images/products/rpc-psu-01.jpg',
            'RPC-PSU-02' => 'images/products/rpc-psu-02.jpg',
            'RPC-MON-01' => 'images/products/rpc-mon-01.jpg',
            'RPC-MON-02' => 'images/products/rpc-mon-02.jpg',
            'RPC-PER-01' => 'images/products/rpc-per-01.jpg',
            'RPC-PER-02' => 'images/products/rpc-per-02.jpg',
            'RPC-PER-03' => 'images/products/rpc-per-03.jpg',
        ];

        // Hardware specification tables, keyed by SKU, rendered on the product page (P4).
        $productSpecs = [
            'RPC-LAP-01' => ['Display' => '15.6" FHD IPS', 'Battery' => '56Wh', 'Weight' => '1.6 kg', 'Warranty' => '2 Years'],
            'RPC-LAP-02' => ['Display' => '14" FHD IPS', 'Battery' => '60Wh', 'Weight' => '1.1 kg', 'Warranty' => '2 Years'],
            'RPC-DSK-01' => ['Chipset' => 'Intel B760', 'Power Supply' => '650W 80+ Bronze', 'Case' => 'Mid Tower', 'Warranty' => '3 Years'],
            'RPC-DSK-02' => ['Form Factor' => 'Mini PC', 'Power' => '90W external adapter', 'Warranty' => '1 Year'],
            'RPC-CPU-01' => ['Socket' => 'LGA1700', 'Cores / Threads' => '10 (6P+4E) / 16', 'TDP' => '65W', 'Included Cooler' => 'Yes'],
            'RPC-CPU-02' => ['Socket' => 'AM5', 'Cores / Threads' => '8 / 16', 'L3 Cache' => '96MB 3D V-Cache', 'TDP' => '120W'],
            'RPC-MBD-01' => ['Socket' => 'LGA1700', 'Memory' => '4x DDR5 DIMM', 'Form Factor' => 'ATX'],
            'RPC-MBD-02' => ['Socket' => 'AM5', 'Memory' => '4x DDR5 DIMM', 'Form Factor' => 'Micro-ATX'],
            'RPC-GPU-01' => ['VRAM' => '8GB GDDR6', 'Length' => '240mm', 'TGP' => '115W'],
            'RPC-GPU-02' => ['VRAM' => '16GB GDDR6', 'Length' => '287mm', 'TGP' => '263W'],
            'RPC-RAM-01' => ['Capacity' => '16GB (2x8GB)', 'Speed' => 'DDR5-5600', 'CAS Latency' => 'CL46'],
            'RPC-RAM-02' => ['Capacity' => '32GB (2x16GB)', 'Speed' => 'DDR5-6000', 'CAS Latency' => 'CL30'],
            'RPC-SSD-01' => ['Capacity' => '1TB', 'Interface' => 'PCIe Gen4 x4 NVMe', 'Form Factor' => 'M.2 2280', 'Sequential Read' => 'Up to 7100MB/s'],
            'RPC-SSD-02' => ['Capacity' => '2TB', 'Interface' => 'SATA III 6Gb/s', 'Form Factor' => '2.5-inch'],
            'RPC-PSU-01' => ['Wattage' => '650W', 'Efficiency' => '80+ Bronze', 'Modularity' => 'Semi-modular'],
            'RPC-PSU-02' => ['Wattage' => '850W', 'Efficiency' => '80+ Gold', 'Modularity' => 'Fully modular'],
            'RPC-MON-01' => ['Panel' => 'IPS', 'Resolution' => '1920x1080', 'Refresh Rate' => '100Hz'],
            'RPC-MON-02' => ['Panel' => 'IPS', 'Resolution' => '2560x1440', 'Refresh Rate' => '165Hz'],
            'RPC-PER-01' => ['Switch Type' => 'Blue (clicky)', 'Backlight' => 'RGB', 'Connection' => 'Wired USB'],
            'RPC-PER-02' => ['Sensor' => 'Optical, 26000 DPI', 'Connection' => '2.4GHz wireless', 'Battery' => 'Up to 120h'],
            'RPC-PER-03' => ['Audio' => '7.1 virtual surround', 'Connection' => 'USB', 'Microphone' => 'Detachable'],
        ];

        // Each product maps to its variants: option spec => price (LKR).
        $products = [
            ['Laptops & Notebooks', 'RPC-LAP-01', 'Vortex 15 Business Laptop', [
                'Intel i5 / 8GB / 512GB SSD' => 245000,
                'Intel i7 / 16GB / 1TB SSD' => 320000,
            ]],
            ['Laptops & Notebooks', 'RPC-LAP-02', 'UltraLite 14 Ultrabook', [
                'Ryzen 5 / 16GB / 512GB SSD' => 285000,
            ]],
            ['Desktop PCs', 'RPC-DSK-01', 'Nova Tower Gaming PC', [
                'RTX 4060 / Intel i5' => 389000,
                'RTX 4070 / Intel i7' => 545000,
            ]],
            ['Desktop PCs', 'RPC-DSK-02', 'Office Mini PC', [
                'Intel i3 / 8GB / 256GB' => 145000,
            ]],
            ['Processors (CPUs)', 'RPC-CPU-01', 'Intel Core i5-14400', [
                '14th Gen / 10 Cores' => 68000,
            ]],
            ['Processors (CPUs)', 'RPC-CPU-02', 'AMD Ryzen 7 7800X3D', [
                '8 Cores / 3D V-Cache' => 165000,
            ]],
            ['Motherboards', 'RPC-MBD-01', 'B760 ATX Motherboard', [
                'LGA1700 / DDR5' => 78000,
            ]],
            ['Motherboards', 'RPC-MBD-02', 'B650M Micro-ATX Motherboard', [
                'AM5 / DDR5' => 72000,
            ]],
            ['Graphics Cards', 'RPC-GPU-01', 'GeForce RTX 4060', [
                '8GB GDDR6' => 185000,
            ]],
            ['Graphics Cards', 'RPC-GPU-02', 'Radeon RX 7800 XT', [
                '16GB GDDR6' => 235000,
            ]],
            ['Memory (RAM)', 'RPC-RAM-01', '16GB DDR5 Memory Kit', [
                '2x8GB / 5600MT/s' => 28500,
            ]],
            ['Memory (RAM)', 'RPC-RAM-02', '32GB DDR5 Memory Kit', [
                '2x16GB / 6000MT/s' => 52000,
            ]],
            ['Storage (SSD & HDD)', 'RPC-SSD-01', '1TB NVMe SSD', [
                'Gen4 M.2' => 42000,
            ]],
            ['Storage (SSD & HDD)', 'RPC-SSD-02', '2TB SATA SSD', [
                '2.5-inch' => 65000,
            ]],
            ['Power Supplies (PSUs)', 'RPC-PSU-01', '650W 80+ Bronze PSU', [
                'Semi-modular' => 32000,
            ]],
            ['Power Supplies (PSUs)', 'RPC-PSU-02', '850W 80+ Gold PSU', [
                'Fully modular' => 54000,
            ]],
            ['Monitors & Displays', 'RPC-MON-01', '24-inch FHD Monitor', [
                'IPS / 100Hz' => 78000,
            ]],
            ['Monitors & Displays', 'RPC-MON-02', '27-inch QHD Monitor', [
                'IPS / 165Hz' => 135000,
            ]],
            ['Peripherals & Accessories', 'RPC-PER-01', 'Mechanical Gaming Keyboard', [
                'Blue switches / RGB' => 24000,
            ]],
            ['Peripherals & Accessories', 'RPC-PER-02', 'Wireless Gaming Mouse', [
                '26K DPI' => 16000,
            ]],
            ['Peripherals & Accessories', 'RPC-PER-03', 'RGB Gaming Headset', [
                '7.1 Surround' => 19000,
            ]],
        ];

        $productSort = 1;
        foreach ($products as [$collection, $sku, $name, $variants]) {
            $product = Product::query()->updateOrCreate([
                'sku' => $sku,
            ], [
                'category_id' => $categoryIds[$collection],
                'name' => $name,
                'slug' => Str::slug($sku.' '.$name),
                'image_path' => $productImages[$sku],
                'seo_description' => "{$name} — genuine computer hardware from Randalu PC in Sri Lanka.",
                'specs' => $productSpecs[$sku] ?? null,
                'sort_order' => $productSort++,
                'is_active' => true,
            ]);

            foreach ($variants as $spec => $price) {
                $product->variants()->updateOrCreate([
                    'size' => $spec,
                    'color' => 'As pictured',
                ], [
                    'price' => $price,
                    'stock_quantity' => 10,
                    'low_stock_threshold' => 2,
                    'is_active' => true,
                ]);
            }
        }

        $settings = [
            'site_url' => 'https://randalu-pc.lk',
            'store_name' => 'Randalu PC',
            'store_phone' => '+94776474542',
            'whatsapp_number' => '94776474542',
            'store_address' => 'Randalu PC, Sri Lanka',
            'google_maps_embed_url' => '',
            'admin_email' => env('ADMIN_EMAIL', 'admin@randalu-pc.lk'),
            'currency' => 'LKR',
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
