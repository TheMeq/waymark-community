<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;

final class DiscardUnattachedSiteMedia
{
    public function __construct(private readonly DeleteSiteMedia $deletion) {}

    public function handle(User $actor, SiteMedia $media): bool
    {
        return $this->deletion->discardOrphan($actor, $media);
    }
}
