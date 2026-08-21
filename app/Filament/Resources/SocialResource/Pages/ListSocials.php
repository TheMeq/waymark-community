<?php

namespace App\Filament\Resources\SocialResource\Pages;

use App\Filament\Resources\SocialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListSocials extends ListRecords
{
    protected static string $resource = SocialResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
