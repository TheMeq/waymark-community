<?php

namespace App\Http\Controllers;

use App\Domain\SiteMedia\Data\SiteMediaStorageReference;
use App\Domain\SiteMedia\Models\SiteMedia;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class SiteMediaStreamController
{
    public function __invoke(SiteMedia $media, string $variant): Response
    {
        $path = is_array($media->processed_variants) ? ($media->processed_variants[$variant] ?? null) : null;
        if ($media->processing_status !== 'complete' || ! is_string($path) || ! SiteMediaStorageReference::isSafe($media->storage_disk, $path) || ! Storage::disk($media->storage_disk)->exists($path)) {
            abort(404);
        }

        return response(Storage::disk($media->storage_disk)->get($path), 200, ['Content-Type' => $media->mime_type, 'Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']);
    }
}
