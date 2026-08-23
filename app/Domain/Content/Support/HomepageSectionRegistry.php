<?php

namespace App\Domain\Content\Support;

final class HomepageSectionRegistry
{
    /** @return array<string, array<int, string>> */
    public static function sections(): array
    {
        return [
            'hero' => ['default', 'compact'],
            'whats_on' => ['default', 'walks_first'],
            'gallery' => ['default', 'feature_first'],
            'join' => ['default', 'resources_first'],
            'testimonial' => ['default'],
            'news' => ['default', 'featured'],
            'holiday' => ['default', 'wide'],
        ];
    }
}
