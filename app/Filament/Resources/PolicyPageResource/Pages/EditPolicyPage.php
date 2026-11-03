<?php

namespace App\Filament\Resources\PolicyPageResource\Pages;

use App\Filament\Pages\EditResourceRecord as EditRecord;
use App\Filament\Resources\PolicyPageResource;

final class EditPolicyPage extends EditRecord
{
    protected static string $resource = PolicyPageResource::class;
}
