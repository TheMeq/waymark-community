<?php

namespace App\Filament\Resources\TestimonialResource\Pages;

use App\Filament\Pages\EditResourceRecord as EditRecord;
use App\Filament\Resources\TestimonialResource;
use Filament\Actions\DeleteAction;

final class EditTestimonial extends EditRecord
{
    protected static string $resource = TestimonialResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
