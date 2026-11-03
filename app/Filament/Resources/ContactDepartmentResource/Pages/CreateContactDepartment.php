<?php

namespace App\Filament\Resources\ContactDepartmentResource\Pages;

use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\ContactDepartmentResource;

final class CreateContactDepartment extends CreateRecord
{
    protected static string $resource = ContactDepartmentResource::class;
}
