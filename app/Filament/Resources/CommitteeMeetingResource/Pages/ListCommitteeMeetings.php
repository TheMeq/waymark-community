<?php

namespace App\Filament\Resources\CommitteeMeetingResource\Pages;

use App\Filament\Resources\CommitteeMeetingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCommitteeMeetings extends ListRecords
{
    protected static string $resource = CommitteeMeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
