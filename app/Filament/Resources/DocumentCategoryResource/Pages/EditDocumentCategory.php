<?php

namespace App\Filament\Resources\DocumentCategoryResource\Pages;

use App\Filament\Pages\EditResourceRecord as EditRecord;
use App\Filament\Resources\DocumentCategoryResource;
use Filament\Actions\DeleteAction;

final class EditDocumentCategory extends EditRecord
{
    protected static string $resource = DocumentCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
