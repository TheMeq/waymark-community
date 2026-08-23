<?php

namespace App\Filament\Resources\CmsPageResource\Pages;

use App\Filament\Resources\CmsPageResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateCmsPage extends CreateRecord
{
    protected static string $resource = CmsPageResource::class;
}
