<?php

namespace App\Filament\Resources\NewsletterResource\Pages;

use App\Filament\Resources\NewsletterResource;
use Filament\Resources\Pages\EditRecord;

final class EditNewsletter extends EditRecord
{
    protected static string $resource = NewsletterResource::class;
}
