<?php

namespace App\Domain\Walks\Enums;

enum DuplicateWalkCopyGroup: string
{
    case CoreDetails = 'core_details';
    case Location = 'location';
    case Gpx = 'gpx';
    case Attachments = 'attachments';
    case FeaturedImage = 'featured_image';
    case OptionalFields = 'optional_fields';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::CoreDetails->value => 'Core details',
            self::Location->value => 'Location',
            self::Gpx->value => 'GPX route',
            self::Attachments->value => 'Attachments',
            self::FeaturedImage->value => 'Featured image',
            self::OptionalFields->value => 'Optional fields',
        ];
    }

    /** @return array<int, string> */
    public static function defaults(): array
    {
        return [self::CoreDetails->value, self::Location->value];
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_keys(self::options());
    }
}
