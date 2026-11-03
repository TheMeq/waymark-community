<?php

namespace App\Filament\Resources\TagResource\Pages;

use App\Domain\Walks\Actions\CreateTag as CreateTagAction;
use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\TagResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class CreateTag extends CreateRecord
{
    protected static string $resource = TagResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();

        return app(CreateTagAction::class)->handle($actor, $data);
    }
}
