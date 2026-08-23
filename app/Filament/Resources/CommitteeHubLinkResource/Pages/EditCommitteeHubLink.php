<?php

namespace App\Filament\Resources\CommitteeHubLinkResource\Pages;

use App\Filament\Resources\CommitteeHubLinkResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditCommitteeHubLink extends EditRecord
{
    protected static string $resource = CommitteeHubLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
