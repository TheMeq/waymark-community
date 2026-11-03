<?php

namespace App\Filament\Resources\ContactDepartmentResource\Pages;

use App\Filament\Pages\EditResourceRecord as EditRecord;
use App\Filament\Resources\ContactDepartmentResource;
use Filament\Actions\DeleteAction;

final class EditContactDepartment extends EditRecord
{
    protected static string $resource = ContactDepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
