<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDescriptionJob;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;

class GenerateAiDescriptions extends Command
{
    protected $signature = 'ai:generate-descriptions
                            {--fresh : Regenerate even if description already exists}
                            {--queue : Dispatch as queued jobs (recommended for free tier)}
                            {--model= : Override OpenRouter model for this run}
                            {--limit= : Limit number of products to process}';

    protected $description = 'Generate AI descriptions for products and categories via OpenRouter (free tier)';

    public function handle(): int
    {
        if ($this->option('model')) {
            config(['services.openrouter.model' => $this->option('model')]);
        }

        $isQueue = (bool) $this->option('queue');
        $isFresh = (bool) $this->option('fresh');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $key = config('services.openrouter.key');
        if (blank($key)) {
            $this->error('OPENROUTER_API_KEY is not set. Set it in .env (e.g. sk-or-v1-... from https://openrouter.ai/keys).');

            return self::FAILURE;
        }

        $products = Product::query()
            ->when(! $isFresh, fn ($q) => $q->where(function ($q) {
                $q->whereNull('seo_description')->orWhereNull('description');
            }))
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $categories = Category::query()
            ->when(! $isFresh, fn ($q) => $q->whereNull('description'))
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $this->info("Found {$products->count()} products and {$categories->count()} categories to process (fresh: ".($isFresh ? 'yes' : 'no').', queue: '.($isQueue ? 'yes' : 'no').')');

        if ($products->isEmpty() && $categories->isEmpty()) {
            $this->info('Nothing to generate. Use --fresh to regenerate.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($products as $index => $product) {
            if ($isQueue) {
                GenerateDescriptionJob::dispatch('product_seo', $product->id)->delay(now()->addSeconds($index * 3));
                GenerateDescriptionJob::dispatch('product_long', $product->id)->delay(now()->addSeconds($index * 3 + 1));
            } else {
                $this->info("Generating SEO for {$product->sku}...");
                GenerateDescriptionJob::dispatchSync('product_seo', $product->id);
                $this->info("Generating long for {$product->sku}...");
                GenerateDescriptionJob::dispatchSync('product_long', $product->id);
                if ($index > 0 && $index % 5 === 0) {
                    sleep(2);
                }
            }
            $count++;
        }

        foreach ($categories as $index => $category) {
            if ($isQueue) {
                GenerateDescriptionJob::dispatch('category', $category->id)->delay(now()->addSeconds(($products->count() * 3) + $index * 3));
            } else {
                $this->info("Generating category {$category->name}...");
                GenerateDescriptionJob::dispatchSync('category', $category->id);
            }
            $count++;
        }

        $mode = $isQueue ? 'queued' : 'generated';
        $this->info("Done — {$mode} {$count} items. Free tier: ~20 RPM, 200/day (buy \$10 credit for 1000/day). Run php artisan queue:work to process queued jobs.");

        return self::SUCCESS;
    }
}
