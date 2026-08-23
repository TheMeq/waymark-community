<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\NavigationItem;
use App\Domain\Operations\Models\SiteProfile;
use Illuminate\Support\Collection;

final class PublicNavigationItems
{
    public function get(): Collection
    {
        $modules = (array) (SiteProfile::query()->find(SiteProfile::SINGLETON_ID)?->module_configuration ?? []);

        return NavigationItem::query()->where('enabled', true)->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (NavigationItem $item): bool => $item->module_key === null || ($modules[$item->module_key] ?? true) === true)
            ->values();
    }
}
