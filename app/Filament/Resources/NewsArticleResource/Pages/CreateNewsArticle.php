<?php

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Filament\Resources\NewsArticleResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateNewsArticle extends CreateRecord
{
    protected static string $resource = NewsArticleResource::class;
}
