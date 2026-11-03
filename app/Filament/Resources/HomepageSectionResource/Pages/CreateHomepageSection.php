<?php

namespace App\Filament\Resources\HomepageSectionResource\Pages;

use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\HomepageSectionResource;

final class CreateHomepageSection extends CreateRecord
{
    protected static string $resource = HomepageSectionResource::class;
}
