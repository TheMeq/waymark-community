<?php

namespace App\Filament\Resources\SpecialAlbumResource\Pages;

use App\Filament\Resources\SpecialAlbumResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListSpecialAlbums extends ListRecords
{
    protected static string $resource = SpecialAlbumResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
