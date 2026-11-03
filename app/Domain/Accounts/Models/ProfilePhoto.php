<?php

namespace App\Domain\Accounts\Models;

use App\Domain\Accounts\Data\ProfilePhotoStorageReference;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['id', 'user_id', 'storage_disk', 'processed_variants', 'mime_type'])]
final class ProfilePhoto extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['processed_variants' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function variantPath(string $variant): ?string
    {
        $path = $this->processed_variants[$variant] ?? null;

        return in_array($variant, ['medium', 'thumbnail'], true)
            && is_string($path)
            && ProfilePhotoStorageReference::isSafe($this->storage_disk, $path)
            && Storage::disk($this->storage_disk)->exists($path) ? $path : null;
    }
}
