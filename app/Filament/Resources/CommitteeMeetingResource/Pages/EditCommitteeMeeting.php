<?php

namespace App\Filament\Resources\CommitteeMeetingResource\Pages;

use App\Filament\Pages\EditResourceRecord as EditRecord;
use App\Filament\Resources\CommitteeMeetingResource;
use Filament\Actions\DeleteAction;

final class EditCommitteeMeeting extends EditRecord
{
    protected static string $resource = CommitteeMeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
