<?php

namespace App\Filament\Resources\FooterSectionResource\Pages;

use App\Filament\Pages\EditResourceRecord as EditRecord;
use App\Filament\Resources\FooterSectionResource;
use Filament\Actions\DeleteAction;

final class EditFooterSection extends EditRecord
{
    protected static string $resource = FooterSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
