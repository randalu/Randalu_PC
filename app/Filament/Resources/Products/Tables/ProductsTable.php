<?php

namespace App\Filament\Resources\Products\Tables;

use App\Jobs\GenerateDescriptionJob;
use App\Models\Product;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name')
                    ->searchable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                ImageColumn::make('image_path')
                    ->label('Image')
                    ->getStateUsing(fn (Product $record): string => asset($record->image_path ?: 'images/product-placeholder.png'))
                    ->square(),
                TextColumn::make('variants_count')
                    ->counts('variants')
                    ->label('Variants')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Collection')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('aiGenerateSeo')
                        ->label('AI Generate SEO')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                GenerateDescriptionJob::dispatch('product_seo', $record->id);
                            }
                            Notification::make()->success()->title('AI SEO jobs queued for '.count($records).' products')->send();
                        }),
                    BulkAction::make('aiGenerateLong')
                        ->label('AI Generate Long Desc')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                GenerateDescriptionJob::dispatch('product_long', $record->id);
                            }
                            Notification::make()->success()->title('AI long description jobs queued')->send();
                        }),
                ]),
            ]);
    }
}
