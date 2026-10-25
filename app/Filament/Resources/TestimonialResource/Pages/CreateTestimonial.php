<?php

namespace App\Filament\Resources\TestimonialResource\Pages;

use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\TestimonialResource;

final class CreateTestimonial extends CreateRecord
{
    protected static string $resource = TestimonialResource::class;
}
