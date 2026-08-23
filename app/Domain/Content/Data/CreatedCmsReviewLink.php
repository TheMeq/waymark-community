<?php

namespace App\Domain\Content\Data;

use App\Domain\Content\Models\CmsReviewLink;

final readonly class CreatedCmsReviewLink
{
    public function __construct(public CmsReviewLink $link, public string $plainToken) {}
}
