<?php

namespace App\Filament\Resources\WalkFieldSettingsResource\Pages;

use App\Domain\Walks\Actions\UpdateWalkFieldSettings;
use App\Filament\Resources\WalkFieldSettingsResource;
use Filament\Resources\Pages\ListRecords;

final class ListWalkFieldSettings extends ListRecords
{
    protected static string $resource = WalkFieldSettingsResource::class;

    public function mount(): void
    {
        WalkFieldSettingsResource::authorizeViewAny();

        app(UpdateWalkFieldSettings::class)->handle([]);

        parent::mount();
    }
}
