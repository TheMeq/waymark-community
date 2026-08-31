<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\PhotoModeration;
use App\Filament\Resources\DocumentResource;
use App\Filament\Resources\HolidayResource;
use App\Filament\Resources\NewsArticleResource;
use App\Filament\Resources\SocialResource;
use App\Filament\Resources\WalkResource;
use Filament\Widgets\Widget;

final class QuickActionsWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.quick-actions';

    protected int|string|array $columnSpan = 'full';

    /** @return array{actions:list<array{label:string,url:string}>} */
    protected function getViewData(): array
    {
        $actions = [];

        foreach ([
            [WalkResource::canCreate(), 'Add a walk', fn (): string => WalkResource::getUrl('create')],
            [SocialResource::canCreate(), 'Add a social', fn (): string => SocialResource::getUrl('create')],
            [HolidayResource::canCreate(), 'Add a holiday', fn (): string => HolidayResource::getUrl('create')],
            [PhotoModeration::canAccess(), 'Manage photos', fn (): string => PhotoModeration::getUrl()],
            [NewsArticleResource::canCreate(), 'Write news', fn (): string => NewsArticleResource::getUrl('create')],
            [DocumentResource::canCreate(), 'Add a document', fn (): string => DocumentResource::getUrl('create')],
        ] as [$allowed, $label, $url]) {
            if ($allowed) {
                $actions[] = ['label' => $label, 'url' => $url()];
            }
        }

        return ['actions' => $actions];
    }
}
