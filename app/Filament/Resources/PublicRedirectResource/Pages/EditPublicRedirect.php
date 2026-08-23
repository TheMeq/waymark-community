<?php

namespace App\Filament\Resources\PublicRedirectResource\Pages;

use App\Filament\Resources\PublicRedirectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditPublicRedirect extends EditRecord
{
    protected static string $resource = PublicRedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
