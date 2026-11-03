<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\DocumentResource;

final class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;
}
