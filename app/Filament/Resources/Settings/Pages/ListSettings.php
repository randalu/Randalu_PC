<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingResource;
use App\Models\Setting;
use App\Services\SmsTestService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSettings extends ListRecords
{
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestSms')
                ->label('Send test SMS')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->modalHeading('Send test SMS')
                ->modalSubmitActionLabel('Send')
                ->schema([
                    TextInput::make('phone')
                        ->label('Phone number')
                        ->default(fn (): ?string => Setting::getValue('store_phone', '+94776474542'))
                        ->required()
                        ->maxLength(40),
                    Textarea::make('message')
                        ->default('PMS SMS test: order status messages are configured.')
                        ->required()
                        ->maxLength(621)
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    try {
                        $sent = app(SmsTestService::class)->send($data['phone'], $data['message'], auth()->id());

                        Notification::make()
                            ->title($sent ? 'Test SMS sent' : 'Test SMS skipped')
                            ->body($sent ? 'The SMS provider accepted the request.' : 'SMS is disabled in settings.')
                            ->color($sent ? 'success' : 'warning')
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('Test SMS failed')
                            ->body($exception->getMessage())
                            ->send();
                    }
                }),
            CreateAction::make(),
        ];
    }
}
