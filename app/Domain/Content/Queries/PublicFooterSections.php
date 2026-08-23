<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\FooterSection;
use Illuminate\Support\Collection;

final class PublicFooterSections
{
    public function get(): Collection
    {
        return FooterSection::query()->where('enabled', true)->orderBy('sort_order')->orderBy('id')->get();
    }
}
