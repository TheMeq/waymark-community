<?php

namespace App\Domain\Governance\Models;

use App\Domain\Content\Models\NavigationItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

#[Fillable(['label', 'url', 'notes', 'sort_order', 'active'])]
final class CommitteeHubLink extends Model
{
    protected static function booted(): void
    {
        self::saving(function (self $link): void {
            if (! NavigationItem::isAllowedUrl($link->url)) {
                throw ValidationException::withMessages(['url' => 'Use a safe site path or secure external URL.']);
            }
        });
    }

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'active' => 'boolean'];
    }
}
