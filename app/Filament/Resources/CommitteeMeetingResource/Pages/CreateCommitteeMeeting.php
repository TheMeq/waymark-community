<?php

namespace App\Filament\Resources\CommitteeMeetingResource\Pages;

use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\CommitteeMeetingResource;

final class CreateCommitteeMeeting extends CreateRecord
{
    protected static string $resource = CommitteeMeetingResource::class;
}
