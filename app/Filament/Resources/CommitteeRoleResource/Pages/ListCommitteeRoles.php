<?php

namespace App\Filament\Resources\CommitteeRoleResource\Pages;

use App\Filament\Resources\CommitteeRoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCommitteeRoles extends ListRecords
{
    protected static string $resource = CommitteeRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
