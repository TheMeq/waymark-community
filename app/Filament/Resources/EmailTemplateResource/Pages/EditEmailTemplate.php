<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Pages\EditResourceRecord as EditRecord;
use App\Filament\Resources\EmailTemplateResource;

final class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;
}
