<?php

namespace App\Filament\Resources\RecurringSeriesResource\Pages;

use App\Filament\Resources\RecurringSeriesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListRecurringSeries extends ListRecords
{
    protected static string $resource = RecurringSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
