<?php

namespace App\Filament\Resources\WalkResource\Pages;

use App\Filament\Resources\WalkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListWalks extends ListRecords
{
    protected static string $resource = WalkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
