<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Walks\Data\GpxStoragePath;
use App\Domain\Walks\Models\Walk;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadWalkGpx
{
    public function handle(Walk $walk): StreamedResponse
    {
        if (! GpxStoragePath::isGenerated($walk->gpx_path)) {
            throw new LogicException('This walk does not have a GPX file.');
        }

        $disk = Storage::disk((string) config('walks.gpx.disk', 'local'));

        if (! $disk->exists($walk->gpx_path)) {
            throw new LogicException('This walk does not have an available GPX file.');
        }

        return $disk->download(
            $walk->gpx_path,
            'walk-route.gpx',
            ['Content-Type' => 'application/gpx+xml'],
        );
    }
}
