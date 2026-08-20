<?php

namespace App\Domain\Operations\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'group_name',
    'short_name',
    'contact_email',
    'timezone',
    'locale',
    'distance_unit',
    'ascent_unit',
    'start_year',
    'logo_path',
    'primary_colour',
    'accent_colour',
    'affiliation_name',
    'affiliation_url',
    'module_configuration',
])]
final class SiteProfile extends Model
{
    public const int SINGLETON_ID = 1;

    public $incrementing = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'id' => self::SINGLETON_ID,
        'timezone' => 'Europe/London',
        'locale' => 'en',
        'distance_unit' => 'miles',
        'ascent_unit' => 'feet',
        'module_configuration' => '[]',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'module_configuration' => 'array',
        ];
    }
}
