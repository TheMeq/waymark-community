<?php

namespace App\Domain\SiteMedia\Actions;

use App\Domain\SiteMedia\Data\SiteMediaMetadata;
use App\Domain\SiteMedia\Enums\SiteMediaPurpose;
use App\Domain\SiteMedia\Models\SiteMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;

final class UploadSiteMedia
{
    use ManagesSiteMedia;

    public function __construct(private readonly CreateSiteMedia $creator) {}

    public function handle(User $actor, UploadedFile $upload, SiteMediaMetadata $metadata): SiteMedia
    {
        $this->authorizeSiteMedia($actor);

        return $this->creator->handle($actor, $upload, $metadata, SiteMediaPurpose::Library);
    }
}
