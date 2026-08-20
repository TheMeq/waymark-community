<?php

namespace App\Domain\Walks\Actions;

use App\Domain\Walks\Models\Walk;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadWalkGpx
{
    public function handle(Walk $walk): StreamedResponse
    {
        if ($walk->gpx_path === null) {
            throw new LogicException('This walk does not have a GPX file.');
        }

        return Storage::disk((string) config('walks.gpx.disk', 'local'))->download(
            $walk->gpx_path,
            'walk-route.gpx',
            ['Content-Type' => 'application/gpx+xml'],
        );
    }
}
