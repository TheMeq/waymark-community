<?php

namespace App\Filament\Resources\CommitteeHubLinkResource\Pages;

use App\Filament\Pages\EditResourceRecord as EditRecord;
use App\Filament\Resources\CommitteeHubLinkResource;
use Filament\Actions\DeleteAction;

final class EditCommitteeHubLink extends EditRecord
{
    protected static string $resource = CommitteeHubLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
