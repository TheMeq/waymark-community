<?php

namespace App\Filament\Resources\PolicyPageResource\Pages;

use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\PolicyPageResource;

final class CreatePolicyPage extends CreateRecord
{
    protected static string $resource = PolicyPageResource::class;
}
