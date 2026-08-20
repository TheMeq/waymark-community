<?php

namespace App\Domain\Walks\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['field_configuration', 'leaders_can_publish_directly'])]
final class WalkFieldSettings extends Model
{
    public const int SINGLETON_ID = 1;

    /** @var array<string, string> */
    public const OPTIONAL_FIELDS = [
        'co_leaders' => 'Co-leaders',
        'terrain_notes' => 'Terrain notes',
        'coordinates' => 'Map coordinates',
        'what3words' => 'What3Words',
        'os_grid_reference' => 'OS grid reference',
        'directions' => 'Directions',
        'parking_notes' => 'Parking notes',
        'public_transport' => 'Public transport information',
        'toilet_information' => 'Toilet information',
        'cafe_pub_information' => 'Café or pub information',
        'dog_guidance' => 'Dog guidance',
        'accessibility_notes' => 'Accessibility notes',
        'kit_checklist' => 'Kit checklist',
        'kit_notes' => 'Kit notes',
        'gpx' => 'GPX upload',
        'route_map' => 'Embedded route map',
        'attachments' => 'Attachments',
        'private_organiser_notes' => 'Private organiser notes',
        'recap' => 'Post-walk recap',
    ];

    public $incrementing = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'id' => self::SINGLETON_ID,
        'field_configuration' => '[]',
        'leaders_can_publish_directly' => false,
    ];

    /** @return array<string, bool> */
    public static function defaultFieldConfiguration(): array
    {
        return array_fill_keys(array_keys(self::OPTIONAL_FIELDS), true);
    }

    public function isEnabled(string $field): bool
    {
        return ($this->field_configuration[$field] ?? false) === true;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'field_configuration' => 'array',
            'leaders_can_publish_directly' => 'boolean',
        ];
    }
}
