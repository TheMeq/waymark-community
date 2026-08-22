<?php

namespace App\Filament\Resources\SpecialAlbumResource\Pages;

use App\Domain\Gallery\Actions\SaveSpecialAlbum;
use App\Filament\Resources\SpecialAlbumResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateSpecialAlbum extends CreateRecord
{
    protected static string $resource = SpecialAlbumResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();

        return app(SaveSpecialAlbum::class)->handle(
            $actor,
            null,
            (string) $data['title'],
            (string) $data['slug'],
            $data['description'] ?? null,
        );
    }
}
