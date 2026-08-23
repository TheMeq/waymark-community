<?php

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Filament\Resources\NewsArticleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

final class EditNewsArticle extends EditRecord
{
    protected static string $resource = NewsArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
