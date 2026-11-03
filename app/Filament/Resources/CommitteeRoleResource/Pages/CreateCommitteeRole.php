<?php

namespace App\Filament\Resources\CommitteeRoleResource\Pages;

use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\CommitteeRoleResource;

final class CreateCommitteeRole extends CreateRecord
{
    protected static string $resource = CommitteeRoleResource::class;
}
