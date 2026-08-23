<?php

namespace App\Filament\Resources\PublicRedirectResource\Pages;

use App\Filament\Resources\PublicRedirectResource;
use Filament\Resources\Pages\CreateRecord;

final class CreatePublicRedirect extends CreateRecord
{
    protected static string $resource = PublicRedirectResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'automatic' => false, 'created_by_user_id' => auth()->id()];
    }
}
