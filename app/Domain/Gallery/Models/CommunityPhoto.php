<?php

namespace App\Domain\Gallery\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Gallery\Data\PhotoStorageReference;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'special_album_id',
    'uploader_id',
    'media_type',
    'processing_status',
    'storage_disk',
    'source_path',
    'processed_variants',
    'width',
    'height',
    'file_size_bytes',
    'caption',
    'photographer_name',
    'moderation_status',
    'published_at',
    'captured_at',
    'focal_point_x',
    'focal_point_y',
    'is_featured',
    'presentation_rotation',
])]
final class CommunityPhoto extends Model
{
    protected static function booted(): void
    {
        self::saving(function (self $photo): void {
            $photo->assertHasExactlyOneSourceContext();
            $photo->assertSafeStorageReferences();
        });
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<SpecialAlbum, $this> */
    public function specialAlbum(): BelongsTo
    {
        return $this->belongsTo(SpecialAlbum::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'processed_variants' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'file_size_bytes' => 'integer',
            'published_at' => 'datetime',
            'captured_at' => 'datetime',
            'focal_point_x' => 'decimal:4',
            'focal_point_y' => 'decimal:4',
            'is_featured' => 'boolean',
            'presentation_rotation' => 'integer',
        ];
    }

    private function assertHasExactlyOneSourceContext(): void
    {
        if (($this->event_id === null) === ($this->special_album_id === null)) {
            throw new \LogicException('A community photo must belong to exactly one event or special album.');
        }
    }

    private function assertSafeStorageReferences(): void
    {
        PhotoStorageReference::from((string) $this->storage_disk, (string) $this->source_path);

        if ($this->processed_variants === null) {
            return;
        }

        if (! is_array($this->processed_variants)) {
            throw new \InvalidArgumentException('Processed community photo variants must be stored as paths.');
        }

        foreach ($this->processed_variants as $path) {
            if (! is_string($path)) {
                throw new \InvalidArgumentException('Processed community photo variants must be stored as paths.');
            }

            PhotoStorageReference::from((string) $this->storage_disk, $path);
        }
    }

    public function presentationRotationStyle(): string
    {
        return 'transform: rotate('.((int) $this->presentation_rotation % 360).'deg)';
    }
}
