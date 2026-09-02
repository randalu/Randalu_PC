<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use App\Services\OpenRouterService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Collection')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(4)
                            ->helperText('Category listing description. Use AI to generate.')
                            ->hintAction(
                                Action::make('generateCategoryAi')
                                    ->icon(Heroicon::OutlinedSparkles)
                                    ->label('Generate with AI')
                                    ->action(function ($livewire, $set, $get) {
                                        try {
                                            $category = $get('id') ? Category::query()->find($get('id')) : new Category(['name' => $get('name') ?? 'Category']);
                                            $text = app(OpenRouterService::class)->generateCategoryDescription($category);
                                            $set('description', $text);
                                            Notification::make()->success()->title('Category description generated')->send();
                                        } catch (\Throwable $e) {
                                            Log::warning('AI category generation failed', ['error' => $e->getMessage()]);
                                            Notification::make()->danger()->title('AI failed')->body($e->getMessage())->send();
                                        }
                                    })
                            )
                            ->columnSpanFull(),
                        TextInput::make('sort_order')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }
}
