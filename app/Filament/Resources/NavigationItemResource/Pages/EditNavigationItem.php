<?php

namespace App\Filament\Resources\NavigationItemResource\Pages;

use App\Filament\Pages\EditResourceRecord as EditRecord;
use App\Filament\Resources\NavigationItemResource;
use Filament\Actions\DeleteAction;

final class EditNavigationItem extends EditRecord
{
    protected static string $resource = NavigationItemResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
