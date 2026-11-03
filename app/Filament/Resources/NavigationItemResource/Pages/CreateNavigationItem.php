<?php

namespace App\Filament\Resources\NavigationItemResource\Pages;

use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\NavigationItemResource;

final class CreateNavigationItem extends CreateRecord
{
    protected static string $resource = NavigationItemResource::class;
}
