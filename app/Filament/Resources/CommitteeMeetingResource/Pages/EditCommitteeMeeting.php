<?php

namespace App\Filament\Resources\CommitteeMeetingResource\Pages;

use App\Filament\Resources\CommitteeMeetingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditCommitteeMeeting extends EditRecord
{
    protected static string $resource = CommitteeMeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
