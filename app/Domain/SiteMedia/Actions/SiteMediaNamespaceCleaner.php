<?php

namespace App\Domain\SiteMedia\Actions;

use Illuminate\Support\Facades\Storage;

class SiteMediaNamespaceCleaner
{
    public function delete(string $disk, string $storageKey): bool
    {
        return Storage::disk($disk)->deleteDirectory('site-media/'.$storageKey);
    }
}
