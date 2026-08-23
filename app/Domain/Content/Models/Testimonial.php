<?php

namespace App\Domain\Content\Models;

use App\Domain\SiteMedia\Models\SiteMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quote', 'display_name', 'image_media_id', 'member_since', 'active', 'featured', 'sort_order'])]
final class Testimonial extends Model
{
    protected function casts(): array
    {
        return ['active' => 'boolean', 'featured' => 'boolean', 'sort_order' => 'integer'];
    }

    public function imageMedia(): BelongsTo
    {
        return $this->belongsTo(SiteMedia::class, 'image_media_id');
    }
}
