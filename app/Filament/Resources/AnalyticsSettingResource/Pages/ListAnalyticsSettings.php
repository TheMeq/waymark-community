<?php

namespace App\Filament\Resources\AnalyticsSettingResource\Pages;

use App\Filament\Resources\AnalyticsSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListAnalyticsSettings extends ListRecords
{
    protected static string $resource = AnalyticsSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->visible(fn (): bool => AnalyticsSettingResource::canCreate())];
    }
}
