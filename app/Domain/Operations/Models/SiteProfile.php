<?php

namespace App\Domain\Operations\Models;

use App\Domain\SiteMedia\Models\SiteMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'group_name',
    'short_name',
    'contact_email',
    'timezone',
    'locale',
    'distance_unit',
    'ascent_unit',
    'start_year',
    'logo_media_id',
    'logo_path',
    'favicon_media_id',
    'favicon_path',
    'hero_default_path',
    'primary_colour',
    'accent_colour',
    'typography_option',
    'social_links',
    'terminology',
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
            'social_links' => 'array',
            'terminology' => 'array',
        ];
    }

    /** @return BelongsTo<SiteMedia, $this> */
    public function logoMedia(): BelongsTo
    {
        return $this->belongsTo(SiteMedia::class, 'logo_media_id');
    }

    /** @return BelongsTo<SiteMedia, $this> */
    public function faviconMedia(): BelongsTo
    {
        return $this->belongsTo(SiteMedia::class, 'favicon_media_id');
    }
}
