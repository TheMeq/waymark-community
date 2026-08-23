<?php

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Filament\Resources\NewsArticleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListNewsArticles extends ListRecords
{
    protected static string $resource = NewsArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
