<?php

namespace App\Filament\Resources\ContactDepartmentResource\Pages;

use App\Filament\Resources\ContactDepartmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListContactDepartments extends ListRecords
{
    protected static string $resource = ContactDepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
