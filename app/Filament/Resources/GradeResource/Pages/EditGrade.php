<?php

namespace App\Filament\Resources\GradeResource\Pages;

use App\Filament\Pages\EditResourceRecord as EditRecord;
use App\Filament\Resources\GradeResource;
use Filament\Actions\DeleteAction;

final class EditGrade extends EditRecord
{
    protected static string $resource = GradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
