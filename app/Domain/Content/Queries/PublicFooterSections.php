<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Models\FooterSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class PublicFooterSections
{
    public function get(): Collection
    {
        if (! Schema::hasTable('footer_sections')) {
            return collect();
        }

        return FooterSection::query()->where('enabled', true)->orderBy('sort_order')->orderBy('id')->get();
    }
}
