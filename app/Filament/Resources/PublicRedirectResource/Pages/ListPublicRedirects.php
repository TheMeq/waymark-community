<?php

namespace App\Filament\Resources\PublicRedirectResource\Pages;

use App\Filament\Resources\PublicRedirectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPublicRedirects extends ListRecords
{
    protected static string $resource = PublicRedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
