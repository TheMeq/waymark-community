<?php

namespace App\Filament\Resources\CommitteeHubLinkResource\Pages;

use App\Filament\Resources\CommitteeHubLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCommitteeHubLinks extends ListRecords
{
    protected static string $resource = CommitteeHubLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
