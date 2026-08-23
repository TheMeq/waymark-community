<?php

namespace App\Filament\Resources\CommitteeRoleResource\Pages;

use App\Filament\Resources\CommitteeRoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditCommitteeRole extends EditRecord
{
    protected static string $resource = CommitteeRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
