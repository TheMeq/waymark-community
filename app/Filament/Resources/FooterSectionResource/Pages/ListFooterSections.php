<?php

namespace App\Filament\Resources\FooterSectionResource\Pages;

use App\Filament\Resources\FooterSectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListFooterSections extends ListRecords
{
    protected static string $resource = FooterSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
