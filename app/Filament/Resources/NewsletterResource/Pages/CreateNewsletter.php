<?php

namespace App\Filament\Resources\NewsletterResource\Pages;

use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\NewsletterResource;

final class CreateNewsletter extends CreateRecord
{
    protected static string $resource = NewsletterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }
}
