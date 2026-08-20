<?php

namespace App\Filament\Resources\WalkResource\Pages;

use App\Domain\Walks\Actions\CreateWalk as CreateWalkAction;
use App\Filament\Resources\WalkResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateWalk extends CreateRecord
{
    protected static string $resource = WalkResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(CreateWalkAction::class)->handle($user, $data);
    }
}
