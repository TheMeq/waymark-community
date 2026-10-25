<?php

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Filament\Pages\CreateResourceRecord as CreateRecord;
use App\Filament\Resources\NewsArticleResource;

final class CreateNewsArticle extends CreateRecord
{
    protected static string $resource = NewsArticleResource::class;
}
