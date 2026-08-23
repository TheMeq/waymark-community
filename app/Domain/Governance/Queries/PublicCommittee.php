<?php

namespace App\Domain\Governance\Queries;

use App\Domain\Governance\Models\CommitteeRole;
use Illuminate\Support\Collection;

final class PublicCommittee
{
    public function get(): Collection
    {
        return CommitteeRole::query()->with(['person', 'publicPhotoMedia'])->where('active', true)->where('publicly_visible', true)->where(fn ($query) => $query->whereNull('start_date')->orWhereDate('start_date', '<=', today()))->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', today()))->orderBy('sort_order')->orderBy('id')->get();
    }
}
