<?php

namespace App\Filament\Resources\CmsPageResource\Pages;

use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\CmsPageResource;

final class CreateCmsPage extends CreateRecord
{
    protected static string $resource = CmsPageResource::class;
}
