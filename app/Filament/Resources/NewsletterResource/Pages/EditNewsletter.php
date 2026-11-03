<?php

namespace App\Filament\Resources\NewsletterResource\Pages;

use App\Filament\Pages\EditResourceRecord as EditRecord;
use App\Filament\Resources\NewsletterResource;

final class EditNewsletter extends EditRecord
{
    protected static string $resource = NewsletterResource::class;
}
