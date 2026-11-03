<?php

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Data\ProfilePhotoStorageReference;
use App\Domain\Accounts\Models\ProfilePhoto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class DeleteProfilePhotoFiles
{
    public function handle(ProfilePhoto $photo): bool
    {
        if (ProfilePhoto::query()->whereKey($photo->id)->whereNotNull('user_id')->exists()) {
            return false;
        }
        $paths = array_values(array_filter((array) $photo->processed_variants, fn ($path): bool => is_string($path) && ProfilePhotoStorageReference::isSafe($photo->storage_disk, $path)));
        try {
            if ($paths !== [] && ! Storage::disk($photo->storage_disk)->delete($paths)) {
                throw new \RuntimeException('Profile photo files could not be deleted.');
            }
            $photo->delete();

            return true;
        } catch (\Throwable $exception) {
            ProfilePhoto::query()->updateOrCreate(['id' => $photo->id], [
                'user_id' => null, 'storage_disk' => $photo->storage_disk,
                'processed_variants' => $photo->processed_variants, 'mime_type' => $photo->mime_type,
            ]);
            Log::warning('Profile photo file cleanup will be retried.', ['photo_id' => $photo->id, 'exception' => $exception]);

            return false;
        }
    }

    public function retry(int $limit = 25): int
    {
        return ProfilePhoto::query()->whereNull('user_id')->oldest()->limit(max(1, min(100, $limit)))->get()
            ->filter(fn (ProfilePhoto $photo): bool => $this->handle($photo))->count();
    }
}
