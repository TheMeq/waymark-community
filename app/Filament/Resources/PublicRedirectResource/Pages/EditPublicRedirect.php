<?php

namespace App\Filament\Resources\PublicRedirectResource\Pages;

use App\Filament\Pages\EditResourceRecord as EditRecord;
use App\Filament\Resources\PublicRedirectResource;
use Filament\Actions\DeleteAction;

final class EditPublicRedirect extends EditRecord
{
    protected static string $resource = PublicRedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
