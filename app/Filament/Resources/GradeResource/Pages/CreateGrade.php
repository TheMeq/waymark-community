<?php

namespace App\Filament\Resources\GradeResource\Pages;

use App\Domain\Walks\Actions\CreateGrade as CreateGradeAction;
use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\GradeResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class CreateGrade extends CreateRecord
{
    protected static string $resource = GradeResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();

        return app(CreateGradeAction::class)->handle($actor, $data);
    }
}
