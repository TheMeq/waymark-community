<?php

namespace App\Domain\SiteMedia\Enums;

use App\Domain\Accounts\Enums\ModuleCapability;
use App\Domain\Content\Models\CmsPage;
use App\Domain\Content\Models\NewsArticle;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Governance\Models\CommitteeRole;
use App\Domain\Holidays\Models\Holiday;

enum ContentImageSlot: string
{
    case Holiday = Holiday::class;
    case Page = CmsPage::class;
    case News = NewsArticle::class;
    case Testimonial = Testimonial::class;
    case Committee = CommitteeRole::class;

    public function mediaColumn(): string
    {
        return match ($this) {
            self::Holiday => 'featured_image_media_id', self::Page => 'hero_media_id',
            self::News => 'featured_media_id', self::Testimonial => 'image_media_id',
            self::Committee => 'public_photo_media_id',
        };
    }

    public function pathColumn(): string
    {
        return match ($this) {
            self::Holiday, self::News => 'featured_image_path', self::Page => 'hero_image_path',
            self::Testimonial => 'image_path', self::Committee => 'public_photo_path',
        };
    }

    public function altColumn(): string
    {
        return match ($this) {
            self::Holiday, self::News => 'featured_image_alt_text', self::Page => 'hero_image_alt_text',
            self::Testimonial => 'image_alt_text', self::Committee => 'public_photo_alt_text',
        };
    }

    public function relation(): string
    {
        return match ($this) {
            self::Holiday, self::News => 'featuredMedia', self::Page => 'heroMedia',
            self::Testimonial => 'imageMedia', self::Committee => 'publicPhotoMedia',
        };
    }

    public function capability(): ModuleCapability
    {
        return match ($this) {
            self::Holiday => ModuleCapability::ManageHolidays,
            self::Committee => ModuleCapability::ManageGovernance,
            default => ModuleCapability::ManageContent,
        };
    }
}
