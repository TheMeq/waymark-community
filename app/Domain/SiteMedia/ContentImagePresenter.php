<?php

namespace App\Domain\SiteMedia;

use App\Domain\Content\Presentation\PublicImageReference;
use App\Domain\SiteMedia\Data\SiteMediaPresentation;
use App\Domain\SiteMedia\Enums\ContentImageSlot;
use Illuminate\Database\Eloquent\Model;

final readonly class ContentImagePresenter
{
    public function __construct(private SiteMediaPresenter $presenter) {}

    public function present(Model $owner, ContentImageSlot $slot, string $variant = 'large'): ?SiteMediaPresentation
    {
        $media = $owner->{$slot->relation()};
        $presentation = $media === null ? null : ($this->presenter->present($media, $variant) ?? $this->presenter->present($media, 'master'));
        if ($presentation !== null) {
            return new SiteMediaPresentation($presentation->id, $presentation->url,
                $owner->{$slot->altColumn()} ?: $presentation->alt,
                $presentation->width, $presentation->height, $presentation->focalPointX, $presentation->focalPointY);
        }
        $url = PublicImageReference::resolve($owner->{$slot->pathColumn()});
        if ($url === null) {
            return null;
        }
        $alt = $owner->{$slot->altColumn()} ?: ($owner->getAttribute('title') ?: $owner->getAttribute('display_name') ?: '');

        return new SiteMediaPresentation(0, $url, $alt, 1, 1, 0.5, 0.5);
    }
}
