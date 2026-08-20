<?php

namespace App\Filament\Resources\WalkFieldSettingsResource\Pages;

use App\Domain\Walks\Actions\UpdateWalkFieldSettings;
use App\Filament\Resources\WalkFieldSettingsResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditWalkFieldSettings extends EditRecord
{
    protected static string $resource = WalkFieldSettingsResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateWalkFieldSettings::class)->handle(
            $data['field_configuration'] ?? [],
            $data['leaders_can_publish_directly'] ?? null,
        );
    }
}
