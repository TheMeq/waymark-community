<?php

namespace App\Filament\Resources\SpecialAlbumResource\Pages;

use App\Domain\Gallery\Actions\SaveSpecialAlbum;
use App\Domain\Gallery\Models\SpecialAlbum;
use App\Filament\Resources\SpecialAlbumResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditSpecialAlbum extends EditRecord
{
    protected static string $resource = SpecialAlbumResource::class;

    protected function getHeaderActions(): array
    {
        return [SpecialAlbumResource::deleteAction()];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();

        /** @var SpecialAlbum $record */
        return app(SaveSpecialAlbum::class)->handle(
            $actor,
            $record,
            (string) $data['title'],
            (string) $data['slug'],
            $data['description'] ?? null,
        );
    }
}
