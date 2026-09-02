<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpenRouterService
{
    public function generateProductSeo(Product $product): string
    {
        $product->loadMissing(['category', 'variants']);

        $prompt = sprintf(
            'Product: %s (%s) in %s. Specs: %s. Variants: %s. Write 2 sentences, SEO-friendly, Sri Lankan market, mention Randalu PC, genuine, warranty, max 160 chars.',
            $product->name,
            $product->sku,
            $product->category?->name ?? 'Hardware',
            json_encode($product->specs ?? []),
            $product->variants->pluck('size')->join(', ') ?: 'standard'
        );

        return $this->complete($prompt, 'seo', $product->sku);
    }

    public function generateProductLong(Product $product): string
    {
        $product->loadMissing(['category', 'variants']);

        $prompt = sprintf(
            'Product: %s (%s) in %s. Specs: %s. Variants: %s. Write 3-4 sentences of marketing copy for Randalu PC (Sri Lanka), genuine, warranty, benefits of specs, friendly tone, max 500 chars, no markdown.',
            $product->name,
            $product->sku,
            $product->category?->name ?? 'Hardware',
            json_encode($product->specs ?? []),
            $product->variants->pluck('size')->join(', ') ?: 'standard'
        );

        return $this->complete($prompt, 'long', $product->sku);
    }

    public function generateCategoryDescription(Category $category): string
    {
        $prompt = sprintf(
            'Category: %s. Write 1 sentence, 12-18 words, e-commerce listing description for Randalu PC (Sri Lanka), hardware store, inviting.',
            $category->name
        );

        return $this->complete($prompt, 'category', $category->slug);
    }

    private function complete(string $prompt, string $type, string $identifier): string
    {
        if (! filter_var(config('services.openrouter.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return $this->fallback($type, $identifier);
        }

        $key = config('services.openrouter.key');
        if (blank($key)) {
            Log::info('OpenRouter disabled: missing API key', ['type' => $type, 'id' => $identifier]);

            return $this->fallback($type, $identifier);
        }

        $models = array_filter(array_merge(
            [config('services.openrouter.model', 'google/gemini-2.0-flash-exp:free')],
            (array) config('services.openrouter.fallbacks', [])
        ));

        $baseUrl = rtrim((string) config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
        $referer = (string) config('services.openrouter.referer', config('app.url'));
        $title = (string) config('services.openrouter.title', config('app.name'));

        $system = 'You are a Sri Lankan e-commerce copywriter for Randalu PC. Write concise, SEO-friendly, genuine hardware descriptions. No markdown, plain text only.';

        foreach ($models as $model) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$key,
                    'HTTP-Referer' => $referer,
                    'X-Title' => $title,
                    'Content-Type' => 'application/json',
                ])
                    ->timeout(30)
                    ->retry(2, 500, function ($exception) {
                        return $exception instanceof RequestException && in_array($exception->response?->status(), [429, 502, 503], true);
                    })
                    ->post($baseUrl.'/chat/completions', [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $system],
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'max_tokens' => (int) config('services.openrouter.max_tokens', 250),
                        'temperature' => 0.7,
                    ]);

                if ($response->failed()) {
                    $status = $response->status();
                    $body = Str::limit($response->body(), 500);
                    Log::warning('OpenRouter request failed', ['model' => $model, 'status' => $status, 'body' => $body, 'type' => $type]);

                    if (in_array($status, [429, 502, 503], true)) {
                        continue;
                    }

                    app(EventLogger::class)->record(
                        type: 'ai.failed',
                        summary: "AI {$type} generation failed ({$status}) for {$identifier}",
                        severity: 'warning',
                        metadata: ['model' => $model, 'status' => $status, 'body' => $body, 'type' => $type],
                    );

                    continue;
                }

                $content = $response->json('choices.0.message.content');
                if (! is_string($content) || trim($content) === '') {
                    Log::warning('OpenRouter empty content', ['model' => $model, 'type' => $type]);

                    continue;
                }

                $clean = $this->cleanContent($content, $type);
                if ($clean === '') {
                    continue;
                }

                $usage = $response->json('usage', []);
                app(EventLogger::class)->record(
                    type: 'ai.generated',
                    summary: "AI {$type} generated for {$identifier} via {$model}",
                    severity: 'info',
                    metadata: ['model' => $model, 'type' => $type, 'identifier' => $identifier, 'usage' => $usage],
                );

                return $clean;
            } catch (\Throwable $exception) {
                Log::warning('OpenRouter exception', ['model' => $model, 'error' => $exception->getMessage(), 'type' => $type]);

                app(EventLogger::class)->record(
                    type: 'ai.failed',
                    summary: "AI {$type} exception for {$identifier}: ".$exception->getMessage(),
                    severity: 'error',
                    metadata: ['model' => $model, 'type' => $type, 'error' => $exception->getMessage()],
                );

                continue;
            }
        }

        return $this->fallback($type, $identifier);
    }

    private function cleanContent(string $content, string $type): string
    {
        // Remove <think> blocks for deepseek-r1
        $content = preg_replace('/<think>.*?<\/think>/s', '', $content) ?? $content;
        $content = trim(strip_tags($content));
        $content = preg_replace('/\s+/', ' ', $content) ?? $content;

        if ($type === 'seo') {
            return Str::limit($content, 160, '');
        }

        if ($type === 'category') {
            return Str::limit($content, 200, '');
        }

        return Str::limit($content, 500, '');
    }

    private function fallback(string $type, string $identifier): string
    {
        if ($type === 'category') {
            return "Explore {$identifier} at Randalu PC — genuine hardware in Sri Lanka.";
        }

        return "{$identifier} — genuine computer hardware from Randalu PC in Sri Lanka.";
    }
}
