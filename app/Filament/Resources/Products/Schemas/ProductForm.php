<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Product;
use App\Services\OpenRouterService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product')
                    ->columns(2)
                    ->schema([
                        Select::make('category_id')
                            ->label('Collection')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        FileUpload::make('image_path')
                            ->label('Image')
                            ->image()
                            ->disk('public')
                            ->directory('products')
                            ->visibility('public')
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state && ! str_starts_with($state, 'storage/') && ! str_starts_with($state, 'images/') ? 'storage/'.$state : $state)
                            ->helperText('Existing catalog images stay in /images; new uploads are saved to storage/products.'),
                        TextInput::make('sort_order')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                        Textarea::make('seo_description')
                            ->rows(3)
                            ->maxLength(160)
                            ->helperText('Meta description, max 160 chars. Use AI to generate.')
                            ->hintAction(
                                Action::make('generateSeoAi')
                                    ->icon(Heroicon::OutlinedSparkles)
                                    ->label('Generate with AI')
                                    ->action(function ($livewire, $set, $get) {
                                        try {
                                            $product = $get('id') ? Product::query()->find($get('id')) : new Product([
                                                'name' => $get('name') ?? 'Product',
                                                'sku' => $get('sku') ?? 'SKU',
                                                'category_id' => $get('category_id'),
                                                'specs' => $get('specs'),
                                            ]);
                                            if ($product && $product->category_id) {
                                                $product->setRelation('category', Category::query()->find($product->category_id));
                                            }
                                            $product->variants = collect([]);
                                            $text = app(OpenRouterService::class)->generateProductSeo($product);
                                            $set('seo_description', $text);
                                            Notification::make()->success()->title('SEO description generated')->body($text)->send();
                                        } catch (\Throwable $e) {
                                            Log::warning('AI SEO generation failed', ['error' => $e->getMessage()]);
                                            Notification::make()->danger()->title('AI failed')->body($e->getMessage())->send();
                                        }
                                    })
                            )
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Long Description (AI)')
                            ->rows(6)
                            ->helperText('Marketing copy for product page. AI-generated, editable.')
                            ->hintAction(
                                Action::make('generateLongAi')
                                    ->icon(Heroicon::OutlinedSparkles)
                                    ->label('Generate with AI')
                                    ->action(function ($livewire, $set, $get) {
                                        try {
                                            $product = $get('id') ? Product::query()->find($get('id')) : new Product([
                                                'name' => $get('name') ?? 'Product',
                                                'sku' => $get('sku') ?? 'SKU',
                                                'category_id' => $get('category_id'),
                                                'specs' => $get('specs'),
                                            ]);
                                            if ($product && $product->category_id) {
                                                $product->setRelation('category', Category::query()->find($product->category_id));
                                            }
                                            $product->variants = collect([]);
                                            $text = app(OpenRouterService::class)->generateProductLong($product);
                                            $set('description', $text);
                                            Notification::make()->success()->title('Long description generated')->send();
                                        } catch (\Throwable $e) {
                                            Log::warning('AI long generation failed', ['error' => $e->getMessage()]);
                                            Notification::make()->danger()->title('AI failed')->body($e->getMessage())->send();
                                        }
                                    })
                            )
                            ->columnSpanFull(),
                        KeyValue::make('specs')
                            ->label('Specifications')
                            ->keyLabel('Spec')
                            ->valueLabel('Details')
                            ->reorderable()
                            ->columnSpanFull()
                            ->helperText('Optional hardware attributes shown as a table on the product page (e.g. Socket → LGA1700).'),
                    ]),
            ]);
    }
}
